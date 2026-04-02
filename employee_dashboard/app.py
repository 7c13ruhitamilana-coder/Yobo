from __future__ import annotations

from datetime import date
from functools import wraps
import os
from pathlib import Path
import re
from typing import Any

import tomllib
from flask import Flask, flash, jsonify, redirect, render_template, request, session, url_for
from werkzeug.security import check_password_hash

try:
    import requests
except Exception:  # pragma: no cover
    requests = None


BASE_DIR = Path(__file__).resolve().parent
ROOT_DIR = BASE_DIR.parent


def load_secrets() -> dict[str, Any]:
    path = ROOT_DIR / "secrets.toml"
    if not path.exists():
        return {}
    try:
        with path.open("rb") as handle:
            return tomllib.load(handle)
    except Exception:
        return {}


def get_dashboard_secret_key() -> str:
    secrets = load_secrets()
    return (
        os.getenv("DASHBOARD_SECRET_KEY")
        or os.getenv("SECRET_KEY")
        or secrets.get("DASHBOARD_SECRET_KEY")
        or secrets.get("SECRET_KEY")
        or "dashboard-dev-secret"
    )


app = Flask(
    __name__,
    template_folder=str(BASE_DIR / "templates"),
    static_folder=str(BASE_DIR / "static"),
)
app.config.update(
    SECRET_KEY=get_dashboard_secret_key(),
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
    SESSION_COOKIE_SECURE=os.getenv("SESSION_COOKIE_SECURE", "0") == "1",
)


def get_supabase_config() -> tuple[str | None, str | None]:
    secrets = load_secrets()
    supabase_url = os.getenv("SUPABASE_URL") or secrets.get("SUPABASE_URL")
    supabase_key = (
        os.getenv("SUPABASE_SERVICE_KEY")
        or os.getenv("SUPABASE_KEY")
        or os.getenv("SUPABASE_ANON_KEY")
        or secrets.get("SUPABASE_SERVICE_KEY")
        or secrets.get("SUPABASE_KEY")
        or secrets.get("SUPABASE_ANON_KEY")
    )
    return supabase_url, supabase_key


def supabase_request(
    method: str,
    table: str,
    *,
    params: dict[str, Any] | None = None,
    payload: Any = None,
    prefer: str | None = None,
) -> Any:
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        raise RuntimeError(
            "Supabase is not configured for the employee dashboard. "
            "Set SUPABASE_URL and SUPABASE_SERVICE_KEY (or SUPABASE_KEY)."
        )

    endpoint = f"{supabase_url.rstrip('/')}/rest/v1/{table}"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    if prefer:
        headers["Prefer"] = prefer

    response = requests.request(
        method=method.upper(),
        url=endpoint,
        headers=headers,
        params=params,
        json=payload,
        timeout=15,
    )
    if response.status_code not in (200, 201, 204):
        detail = response.text.strip() or "Unknown error"
        raise RuntimeError(
            f"Supabase request to '{table}' failed with {response.status_code}: {detail}"
        )
    if response.status_code == 204 or not response.text.strip():
        return None
    try:
        return response.json()
    except ValueError:
        return response.text


def normalize_username(value: str) -> str:
    cleaned = re.sub(r"[^a-z0-9._-]+", "", str(value or "").strip().lower())
    return cleaned[:64]


def parse_iso_date(raw: Any) -> date | None:
    value = str(raw or "").strip()
    if not value:
        return None
    try:
        return date.fromisoformat(value[:10])
    except ValueError:
        return None


def booking_length_days(start_date: date | None, end_date: date | None) -> int:
    if not start_date or not end_date:
        return 0
    return max((end_date - start_date).days, 1)


def coerce_bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value
    normalized = str(value or "").strip().lower()
    return normalized in {"1", "true", "yes", "y", "confirmed"}


def normalize_payment_status(value: Any) -> str:
    raw = str(value or "").strip()
    if not raw:
        return "Pending"
    lowered = raw.lower()
    aliases = {
        "paid": "Paid",
        "pending": "Pending",
        "failed": "Failed",
        "partially paid": "Partially Paid",
        "partial": "Partially Paid",
    }
    return aliases.get(lowered, raw.title())


def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not session.get("employee"):
            return redirect(url_for("login"))
        return view(*args, **kwargs)

    return wrapped


def fetch_employee_record(username: str) -> dict[str, Any] | None:
    normalized = normalize_username(username)
    if not normalized:
        return None
    rows = supabase_request(
        "GET",
        "employee_users",
        params={
            "select": "id,biz_id,username,password_hash,full_name,company_name,is_active",
            "username": f"eq.{normalized}",
            "limit": 1,
        },
    )
    if not isinstance(rows, list) or not rows:
        return None
    return rows[0]


def fetch_rows(table: str, *, biz_id: str, order: str | None = None) -> list[dict[str, Any]]:
    params: dict[str, Any] = {"select": "*", "limit": 1000}
    if biz_id:
        params["biz_id"] = f"eq.{biz_id}"
    if order:
        params["order"] = order
    rows = supabase_request("GET", table, params=params)
    return rows if isinstance(rows, list) else []


def resolve_car_model(
    booking: dict[str, Any], fleet_index: dict[str, dict[str, Any]]
) -> str:
    explicit = str(
        booking.get("car_model")
        or booking.get("vehicle_model")
        or booking.get("car_name")
        or ""
    ).strip()
    if explicit:
        return explicit

    car_id = booking.get("car_id")
    fleet_row = fleet_index.get(str(car_id))
    if not fleet_row:
        return f"Car #{car_id}" if car_id not in (None, "") else "Not assigned"

    make = str(fleet_row.get("make") or "").strip()
    model_name = str(fleet_row.get("model") or "").strip()
    resolved = f"{make} {model_name}".strip()
    return resolved or f"Car #{car_id}"


def resolve_confirmation_value(
    booking: dict[str, Any], state: dict[str, Any] | None
) -> bool:
    if state and state.get("is_confirmed") is not None:
        return coerce_bool(state.get("is_confirmed"))
    if booking.get("is_confirmed") is not None:
        return coerce_bool(booking.get("is_confirmed"))
    if booking.get("confirmed") is not None:
        return coerce_bool(booking.get("confirmed"))
    status_text = str(booking.get("confirmation_status") or "").strip().lower()
    if status_text:
        return status_text == "confirmed"
    return False


def build_dashboard_item(
    booking: dict[str, Any],
    state: dict[str, Any] | None,
    fleet_index: dict[str, dict[str, Any]],
) -> dict[str, Any]:
    start_date = parse_iso_date(booking.get("start_date"))
    end_date = parse_iso_date(booking.get("end_date"))
    is_confirmed = resolve_confirmation_value(booking, state)
    payment_status = normalize_payment_status(
        (state or {}).get("payment_status") or booking.get("payment_status")
    )
    car_model = resolve_car_model(booking, fleet_index)
    customer_name = str(booking.get("customer_name") or "Unnamed customer").strip()
    phone = str(booking.get("phone") or "").strip()
    city = str(booking.get("city") or "").strip()
    location = str(booking.get("location") or "").strip()
    insurance = str(booking.get("insurance") or "").strip()
    total_price = booking.get("total_price")
    if total_price in (None, ""):
        total_price = 0

    item = {
        "id": str(booking.get("id") or ""),
        "customer_name": customer_name,
        "phone": phone,
        "city": city,
        "location": location,
        "insurance": insurance or "No insurance",
        "from_date": start_date.isoformat() if start_date else "",
        "to_date": end_date.isoformat() if end_date else "",
        "booking_length_days": booking_length_days(start_date, end_date),
        "booking_length_label": (
            f"{booking_length_days(start_date, end_date)} day(s)"
            if start_date and end_date
            else "Dates pending"
        ),
        "car_model": car_model,
        "payment_status": payment_status,
        "is_confirmed": is_confirmed,
        "confirmation_label": "Confirmed" if is_confirmed else "Pending",
        "total_price": total_price,
        "_start_date": start_date,
        "_end_date": end_date,
    }
    search_fields = [
        item["customer_name"],
        item["phone"],
        item["city"],
        item["location"],
        item["insurance"],
        item["car_model"],
        item["payment_status"],
        item["confirmation_label"],
        item["id"],
    ]
    item["_search_blob"] = " ".join(search_fields).lower()
    return item


def fetch_bookings_for_dashboard(biz_id: str) -> tuple[list[dict[str, Any]], str | None]:
    bookings = fetch_rows("bookings", biz_id=biz_id, order="start_date.asc")

    warning_parts: list[str] = []
    try:
        fleet_rows = fetch_rows("fleet", biz_id=biz_id, order="model.asc")
    except RuntimeError as exc:
        fleet_rows = []
        warning_parts.append(str(exc))

    try:
        state_rows = fetch_rows("booking_admin_states", biz_id=biz_id, order="updated_at.desc")
    except RuntimeError as exc:
        state_rows = []
        warning_parts.append(
            "Booking status table is missing or unavailable. "
            "Run employee_dashboard/supabase_schema.sql to enable confirmation and payment tracking."
        )
        warning_parts.append(str(exc))

    fleet_index = {
        str(row.get("id")): row for row in fleet_rows if row.get("id") not in (None, "")
    }
    state_index = {
        str(row.get("booking_id")): row
        for row in state_rows
        if row.get("booking_id") not in (None, "")
    }

    items = [
        build_dashboard_item(booking, state_index.get(str(booking.get("id"))), fleet_index)
        for booking in bookings
    ]
    items.sort(
        key=lambda item: (
            item["_start_date"] is None,
            item["_start_date"] or date.max,
            item["customer_name"].lower(),
        )
    )
    warning = "\n".join(dict.fromkeys(part for part in warning_parts if part))
    return items, warning or None


def apply_dashboard_filters(items: list[dict[str, Any]]) -> list[dict[str, Any]]:
    search_value = request.args.get("q", "").strip().lower()
    start_filter = parse_iso_date(request.args.get("start_date"))
    end_filter = parse_iso_date(request.args.get("end_date"))
    car_model_filter = request.args.get("car_model", "").strip().lower()
    confirmation_filter = request.args.get("confirmation", "all").strip().lower()
    payment_filter = request.args.get("payment", "all").strip().lower()

    filtered = items
    if search_value:
        filtered = [item for item in filtered if search_value in item["_search_blob"]]
    if car_model_filter:
        filtered = [
            item
            for item in filtered
            if item["car_model"].strip().lower() == car_model_filter
        ]
    if confirmation_filter == "confirmed":
        filtered = [item for item in filtered if item["is_confirmed"]]
    elif confirmation_filter == "pending":
        filtered = [item for item in filtered if not item["is_confirmed"]]
    if payment_filter != "all":
        filtered = [
            item
            for item in filtered
            if item["payment_status"].strip().lower() == payment_filter
        ]
    if start_filter or end_filter:
        overlap_filtered = []
        for item in filtered:
            item_start = item["_start_date"]
            item_end = item["_end_date"]
            if not item_start or not item_end:
                continue
            if start_filter and item_end < start_filter:
                continue
            if end_filter and item_start > end_filter:
                continue
            overlap_filtered.append(item)
        filtered = overlap_filtered
    return filtered


def summarize_items(items: list[dict[str, Any]]) -> dict[str, int]:
    return {
        "total": len(items),
        "confirmed": sum(1 for item in items if item["is_confirmed"]),
        "pending": sum(1 for item in items if not item["is_confirmed"]),
        "paid": sum(1 for item in items if item["payment_status"].lower() == "paid"),
    }


def serialize_items(items: list[dict[str, Any]]) -> list[dict[str, Any]]:
    serialized = []
    for item in items:
        row = {key: value for key, value in item.items() if not key.startswith("_")}
        serialized.append(row)
    return serialized


@app.route("/")
def home():
    if session.get("employee"):
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    if session.get("employee"):
        return redirect(url_for("dashboard"))

    if request.method == "POST":
        username = normalize_username(request.form.get("username", ""))
        password = request.form.get("password", "")

        if not username or not password:
            flash("Enter both username and password to continue.", "error")
            return render_template("login.html", username=username)

        try:
            employee = fetch_employee_record(username)
        except RuntimeError as exc:
            flash(str(exc), "error")
            return render_template("login.html", username=username)

        if not employee or not employee.get("is_active", True):
            flash("That employee account was not found.", "error")
            return render_template("login.html", username=username)
        if not str(employee.get("biz_id") or "").strip():
            flash("This employee account is missing a biz_id.", "error")
            return render_template("login.html", username=username)

        password_hash = str(employee.get("password_hash") or "")
        if not password_hash or not check_password_hash(password_hash, password):
            flash("Incorrect username or password.", "error")
            return render_template("login.html", username=username)

        session["employee"] = {
            "id": str(employee.get("id") or ""),
            "biz_id": str(employee.get("biz_id") or ""),
            "username": str(employee.get("username") or ""),
            "full_name": str(employee.get("full_name") or ""),
            "company_name": str(employee.get("company_name") or ""),
        }
        return redirect(url_for("dashboard"))

    return render_template("login.html", username="")


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/dashboard")
@login_required
def dashboard():
    return render_template("dashboard.html", employee=session.get("employee", {}))


@app.route("/api/dashboard/bookings")
@login_required
def api_dashboard_bookings():
    employee = session.get("employee", {})
    biz_id = str(employee.get("biz_id") or "").strip()
    if not biz_id:
        return jsonify(
            {"ok": False, "error": "This employee account does not have a biz_id."}
        ), 400

    try:
        items, warning = fetch_bookings_for_dashboard(biz_id)
        filtered_items = apply_dashboard_filters(items)
    except RuntimeError as exc:
        return jsonify({"ok": False, "error": str(exc)}), 500

    models = sorted({item["car_model"] for item in items if item["car_model"]})
    return jsonify(
        {
            "ok": True,
            "items": serialize_items(filtered_items),
            "models": models,
            "summary": summarize_items(filtered_items),
            "warning": warning,
        }
    )


@app.route("/api/dashboard/bookings/<booking_id>/state", methods=["POST"])
@login_required
def api_update_booking_state(booking_id: str):
    employee = session.get("employee", {})
    biz_id = str(employee.get("biz_id") or "").strip()
    if not booking_id or not biz_id:
        return jsonify({"ok": False, "error": "Missing booking or company information."}), 400

    body = request.get_json(force=True, silent=True) or {}
    update_payload: dict[str, Any] = {
        "booking_id": str(booking_id),
        "biz_id": biz_id,
        "updated_by": employee.get("username") or employee.get("full_name") or "employee",
    }

    if "is_confirmed" in body:
        update_payload["is_confirmed"] = coerce_bool(body.get("is_confirmed"))
    if "payment_status" in body:
        update_payload["payment_status"] = normalize_payment_status(body.get("payment_status"))
    if len(update_payload) == 3:
        return jsonify({"ok": False, "error": "No state changes were provided."}), 400

    try:
        rows = supabase_request(
            "POST",
            "booking_admin_states",
            params={"on_conflict": "booking_id,biz_id"},
            payload=update_payload,
            prefer="resolution=merge-duplicates,return=representation",
        )
    except RuntimeError as exc:
        return jsonify({"ok": False, "error": str(exc)}), 500

    state_row = rows[0] if isinstance(rows, list) and rows else update_payload
    return jsonify(
        {
            "ok": True,
            "state": {
                "booking_id": str(state_row.get("booking_id") or booking_id),
                "is_confirmed": coerce_bool(state_row.get("is_confirmed")),
                "payment_status": normalize_payment_status(state_row.get("payment_status")),
            },
        }
    )


if __name__ == "__main__":
    port = int(os.getenv("PORT", "8600"))
    debug = os.getenv("FLASK_DEBUG", "1") == "1"
    app.run(host="0.0.0.0", port=port, debug=debug)
