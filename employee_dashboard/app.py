from __future__ import annotations

from datetime import date, datetime, timezone
from functools import wraps
import os
from pathlib import Path
import re
from typing import Any

import tomllib
from flask import Flask, flash, jsonify, redirect, render_template, request, session, url_for

try:
    import requests
except Exception:  # pragma: no cover
    requests = None


BASE_DIR = Path(__file__).resolve().parent
ROOT_DIR = BASE_DIR.parent
ACCESS_MANAGER_ROLES = {"owner", "admin", "manager"}
ASSIGNABLE_ROLES = {"staff", "manager", "admin", "owner"}


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


def get_supabase_url() -> str | None:
    secrets = load_secrets()
    return os.getenv("SUPABASE_URL") or secrets.get("SUPABASE_URL")


def get_supabase_service_key() -> str | None:
    secrets = load_secrets()
    return (
        os.getenv("SUPABASE_SERVICE_KEY")
        or os.getenv("SUPABASE_KEY")
        or secrets.get("SUPABASE_SERVICE_KEY")
        or secrets.get("SUPABASE_KEY")
    )


def get_supabase_public_key() -> str | None:
    secrets = load_secrets()
    return (
        os.getenv("SUPABASE_PUBLISHABLE_KEY")
        or os.getenv("SUPABASE_ANON_KEY")
        or secrets.get("SUPABASE_PUBLISHABLE_KEY")
        or secrets.get("SUPABASE_ANON_KEY")
        or get_supabase_service_key()
    )


def parse_response_payload(response: requests.Response) -> Any:
    if response.status_code == 204 or not response.text.strip():
        return None
    try:
        return response.json()
    except ValueError:
        return response.text


def extract_error_message(payload: Any, fallback: str) -> str:
    if isinstance(payload, dict):
        for key in ("msg", "message", "error_description", "error", "hint"):
            value = payload.get(key)
            if value:
                return str(value)
    if isinstance(payload, str) and payload.strip():
        return payload.strip()
    return fallback


def supabase_rest_request(
    method: str,
    table: str,
    *,
    params: dict[str, Any] | None = None,
    payload: Any = None,
    prefer: str | None = None,
) -> Any:
    supabase_url = get_supabase_url()
    supabase_key = get_supabase_service_key()
    if not supabase_url or not supabase_key or not requests:
        raise RuntimeError(
            "Supabase is not configured for the employee dashboard. "
            "Set SUPABASE_URL and SUPABASE_SERVICE_KEY."
        )

    endpoint = f"{supabase_url.rstrip('/')}/rest/v1/{table}"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    if prefer:
        headers["Prefer"] = prefer

    try:
        response = requests.request(
            method=method.upper(),
            url=endpoint,
            headers=headers,
            params=params,
            json=payload,
            timeout=15,
        )
    except Exception as exc:
        raise RuntimeError(f"Could not reach Supabase for '{table}': {exc}") from exc

    body = parse_response_payload(response)
    if response.status_code not in (200, 201, 204):
        detail = extract_error_message(body, "Unknown error")
        raise RuntimeError(
            f"Supabase request to '{table}' failed with {response.status_code}: {detail}"
        )
    return body


def supabase_auth_request(
    method: str,
    path: str,
    *,
    payload: dict[str, Any] | None = None,
    admin: bool = False,
) -> Any:
    supabase_url = get_supabase_url()
    if not supabase_url or not requests:
        raise RuntimeError(
            "Supabase is not configured for the employee dashboard. "
            "Set SUPABASE_URL and the required auth keys."
        )

    key = get_supabase_service_key() if admin else get_supabase_public_key()
    if not key:
        raise RuntimeError(
            "Supabase auth is missing an API key. "
            "Set SUPABASE_ANON_KEY (or SUPABASE_PUBLISHABLE_KEY) and SUPABASE_SERVICE_KEY."
        )

    endpoint = f"{supabase_url.rstrip('/')}/auth/v1/{path.lstrip('/')}"
    headers = {
        "apikey": key,
        "Content-Type": "application/json",
    }
    if admin:
        headers["Authorization"] = f"Bearer {key}"

    try:
        response = requests.request(
            method=method.upper(),
            url=endpoint,
            headers=headers,
            json=payload,
            timeout=15,
        )
    except Exception as exc:
        raise RuntimeError(f"Could not reach Supabase Auth for '{path}': {exc}") from exc

    body = parse_response_payload(response)
    if response.status_code not in (200, 201, 204):
        detail = extract_error_message(body, "Unknown error")
        raise RuntimeError(detail)
    return body


def normalize_email(value: str) -> str:
    return str(value or "").strip().lower()


def normalize_role(value: Any) -> str:
    role = str(value or "").strip().lower()
    return role if role in ASSIGNABLE_ROLES else "staff"


def is_valid_email(value: str) -> bool:
    return bool(re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", normalize_email(value)))


def validate_password(password: str) -> str | None:
    if len(password) < 10:
        return "Choose a password with at least 10 characters."
    if not re.search(r"[A-Za-z]", password):
        return "Choose a password that includes at least one letter."
    if not re.search(r"\d", password):
        return "Choose a password that includes at least one number."
    return None


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


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not session.get("employee"):
            wants_json = request.path.startswith("/api/") or (
                "application/json" in request.headers.get("Accept", "").lower()
            )
            if wants_json:
                return jsonify(
                    {
                        "ok": False,
                        "error": "Your dashboard session has expired. Please sign in again.",
                        "redirect_to": url_for("login"),
                    }
                ), 401
            return redirect(url_for("login"))
        return view(*args, **kwargs)

    return wrapped


def employee_can_manage_access(employee: dict[str, Any]) -> bool:
    return normalize_role(employee.get("role")) in ACCESS_MANAGER_ROLES


def can_assign_role(actor_role: str, target_role: str) -> bool:
    actor = normalize_role(actor_role)
    target = normalize_role(target_role)
    if actor == "owner":
        return target in {"staff", "manager", "admin", "owner"}
    if actor == "admin":
        return target in {"staff", "manager"}
    if actor == "manager":
        return target == "staff"
    return False


def fetch_rows(table: str, *, biz_id: str, order: str | None = None) -> list[dict[str, Any]]:
    params: dict[str, Any] = {"select": "*", "limit": 1000}
    if biz_id:
        params["biz_id"] = f"eq.{biz_id}"
    if order:
        params["order"] = order
    rows = supabase_rest_request("GET", table, params=params)
    return rows if isinstance(rows, list) else []


def fetch_single_row(table: str, params: dict[str, Any]) -> dict[str, Any] | None:
    rows = supabase_rest_request("GET", table, params={**params, "limit": 1})
    if isinstance(rows, list) and rows:
        return rows[0]
    return None


def fetch_employee_profile_by_user_id(user_id: str) -> dict[str, Any] | None:
    if not user_id:
        return None
    return fetch_single_row(
        "employee_profiles",
        {
            "select": "id,biz_id,email,full_name,company_name,role,is_active,created_at",
            "id": f"eq.{user_id}",
        },
    )


def fetch_employee_profile_by_email(email: str) -> dict[str, Any] | None:
    normalized = normalize_email(email)
    if not normalized:
        return None
    return fetch_single_row(
        "employee_profiles",
        {
            "select": "id,biz_id,email,full_name,company_name,role,is_active,created_at",
            "email": f"eq.{normalized}",
        },
    )


def fetch_employee_invite(email: str) -> dict[str, Any] | None:
    normalized = normalize_email(email)
    if not normalized:
        return None
    return fetch_single_row(
        "employee_invites",
        {
            "select": "email,biz_id,company_name,full_name,role,is_active,accepted_at",
            "email": f"eq.{normalized}",
        },
    )


def upsert_employee_profile(
    *,
    user_id: str,
    invite: dict[str, Any],
    email: str,
    full_name: str,
) -> dict[str, Any]:
    payload = {
        "id": user_id,
        "biz_id": str(invite.get("biz_id") or "").strip(),
        "email": normalize_email(email),
        "full_name": full_name.strip() or str(invite.get("full_name") or "").strip(),
        "company_name": str(invite.get("company_name") or "").strip(),
        "role": normalize_role(invite.get("role")),
        "is_active": coerce_bool(invite.get("is_active", True)),
        "invited_at": invite.get("accepted_at") or now_iso(),
        "updated_at": now_iso(),
    }
    rows = supabase_rest_request(
        "POST",
        "employee_profiles",
        params={"on_conflict": "id"},
        payload=payload,
        prefer="resolution=merge-duplicates,return=representation",
    )
    if isinstance(rows, list) and rows:
        return rows[0]
    return payload


def mark_invite_accepted(email: str) -> None:
    supabase_rest_request(
        "PATCH",
        "employee_invites",
        params={"email": f"eq.{normalize_email(email)}"},
        payload={"accepted_at": now_iso(), "is_active": True},
        prefer="return=representation",
    )


def sign_up_employee(email: str, password: str, full_name: str, company_name: str) -> dict[str, Any]:
    data = supabase_auth_request(
        "POST",
        "signup",
        payload={
            "email": normalize_email(email),
            "password": password,
            "data": {
                "full_name": full_name.strip(),
                "company_name": company_name.strip(),
            },
        },
    )
    if not isinstance(data, dict):
        return {}
    if "user" in data and isinstance(data.get("user"), dict):
        return data
    if data.get("id"):
        return {"user": data, "session": data.get("session")}
    return data


def sign_in_employee(email: str, password: str) -> dict[str, Any]:
    data = supabase_auth_request(
        "POST",
        "token?grant_type=password",
        payload={"email": normalize_email(email), "password": password},
    )
    return data if isinstance(data, dict) else {}


def request_password_recovery(email: str) -> None:
    supabase_auth_request(
        "POST",
        "recover",
        payload={"email": normalize_email(email)},
    )


def build_employee_session(profile: dict[str, Any], auth_user: dict[str, Any] | None = None) -> dict[str, Any]:
    email = normalize_email((auth_user or {}).get("email") or profile.get("email") or "")
    return {
        "id": str(profile.get("id") or ""),
        "biz_id": str(profile.get("biz_id") or ""),
        "email": email,
        "full_name": str(profile.get("full_name") or "").strip(),
        "company_name": str(profile.get("company_name") or "").strip(),
        "role": normalize_role(profile.get("role")),
    }


def ensure_profile_from_invite(
    auth_user: dict[str, Any],
    email: str,
) -> dict[str, Any] | None:
    user_id = str(auth_user.get("id") or "").strip()
    normalized_email = normalize_email(email)
    if not user_id or not normalized_email:
        return None

    invite = fetch_employee_invite(normalized_email)
    if not invite or not coerce_bool(invite.get("is_active", True)):
        return None

    metadata = auth_user.get("user_metadata") or {}
    full_name = str(
        metadata.get("full_name")
        or invite.get("full_name")
        or normalized_email
    ).strip()

    return upsert_employee_profile(
        user_id=user_id,
        invite=invite,
        email=normalized_email,
        full_name=full_name,
    )


def resolve_car_model(
    booking: dict[str, Any], fleet_index: dict[str, dict[str, Any]]
) -> str:
    explicit = str(
        booking.get("service_name")
        or booking.get("appointment_type")
        or booking.get("car_model")
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
    appointment_time = str(booking.get("appointment_time") or "").strip()
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
            appointment_time
            or (
                f"{booking_length_days(start_date, end_date)} day(s)"
                if start_date and end_date
                else "Dates pending"
            )
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
        appointment_time,
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

    email = normalize_email(request.values.get("email", ""))
    if request.method == "POST":
        password = request.form.get("password", "")

        if not email or not password:
            flash("Enter both email and password to continue.", "error")
            return render_template("login.html", email=email)
        if not is_valid_email(email):
            flash("Enter a valid work email address.", "error")
            return render_template("login.html", email=email)

        try:
            auth_data = sign_in_employee(email, password)
            user = auth_data.get("user") or {}
            profile = fetch_employee_profile_by_user_id(str(user.get("id") or ""))
            if not profile:
                profile = fetch_employee_profile_by_email(email)
            if not profile:
                profile = ensure_profile_from_invite(user, email)
        except RuntimeError as exc:
            flash(str(exc), "error")
            return render_template("login.html", email=email)

        if not profile or not profile.get("is_active", True):
            flash(
                "This email does not have dashboard access yet. "
                "Ask a manager to invite your work email first.",
                "error",
            )
            return render_template("login.html", email=email)
        if not str(profile.get("biz_id") or "").strip():
            flash("This employee profile is missing a biz_id.", "error")
            return render_template("login.html", email=email)

        session["employee"] = build_employee_session(profile, user)
        return redirect(url_for("dashboard"))

    return render_template("login.html", email=email)


@app.route("/register", methods=["GET", "POST"])
def register():
    if session.get("employee"):
        return redirect(url_for("dashboard"))

    email = normalize_email(request.form.get("email", ""))
    full_name = str(request.form.get("full_name", "")).strip()
    if request.method == "POST":
        password = request.form.get("password", "")
        confirm_password = request.form.get("confirm_password", "")

        if not full_name or not email or not password or not confirm_password:
            flash("Complete every field before creating the account.", "error")
            return render_template("register.html", email=email, full_name=full_name)
        if not is_valid_email(email):
            flash("Enter a valid work email address.", "error")
            return render_template("register.html", email=email, full_name=full_name)
        if password != confirm_password:
            flash("The password confirmation does not match.", "error")
            return render_template("register.html", email=email, full_name=full_name)

        password_error = validate_password(password)
        if password_error:
            flash(password_error, "error")
            return render_template("register.html", email=email, full_name=full_name)

        try:
            invite = fetch_employee_invite(email)
            if not invite or not coerce_bool(invite.get("is_active", True)):
                flash(
                    "That email has not been invited yet. "
                    "Ask a manager to create dashboard access for you first.",
                    "error",
                )
                return render_template("register.html", email=email, full_name=full_name)

            existing_profile = fetch_employee_profile_by_email(email)
            if existing_profile:
                flash("That email already has dashboard access. Sign in instead.", "error")
                return render_template("register.html", email=email, full_name=full_name)

            auth_data = sign_up_employee(
                email=email,
                password=password,
                full_name=full_name,
                company_name=str(invite.get("company_name") or ""),
            )
            user = auth_data.get("user") or {}
            user_id = str(user.get("id") or "")
            if not user_id:
                raise RuntimeError("Supabase did not return a new employee user.")

            upsert_employee_profile(
                user_id=user_id,
                invite=invite,
                email=email,
                full_name=full_name,
            )
            mark_invite_accepted(email)
        except RuntimeError as exc:
            flash(str(exc), "error")
            return render_template("register.html", email=email, full_name=full_name)

        flash(
            "Employee account created. If email confirmation is enabled in Supabase, "
            "check your inbox first, then sign in.",
            "success",
        )
        return redirect(url_for("login", email=email))

    return render_template("register.html", email=email, full_name=full_name)


@app.route("/recover", methods=["GET", "POST"])
def recover():
    if session.get("employee"):
        return redirect(url_for("dashboard"))

    email = normalize_email(request.form.get("email", ""))
    if request.method == "POST":
        if not email or not is_valid_email(email):
            flash("Enter a valid work email address first.", "error")
            return render_template("recover.html", email=email)
        try:
            request_password_recovery(email)
        except RuntimeError as exc:
            flash(str(exc), "error")
            return render_template("recover.html", email=email)

        flash(
            "If that email has dashboard access, Supabase has sent a recovery email.",
            "success",
        )
        return redirect(url_for("login", email=email))

    return render_template("recover.html", email=email)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/dashboard")
@login_required
def dashboard():
    employee = session.get("employee", {})
    return render_template(
        "dashboard.html",
        employee=employee,
        can_manage_access=employee_can_manage_access(employee),
        register_url=url_for("register", _external=True),
    )


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
        "updated_by": employee.get("email") or employee.get("full_name") or "employee",
    }

    if "is_confirmed" in body:
        update_payload["is_confirmed"] = coerce_bool(body.get("is_confirmed"))
    if "payment_status" in body:
        update_payload["payment_status"] = normalize_payment_status(body.get("payment_status"))
    if len(update_payload) == 3:
        return jsonify({"ok": False, "error": "No state changes were provided."}), 400

    try:
        rows = supabase_rest_request(
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


@app.route("/api/dashboard/invites", methods=["POST"])
@login_required
def api_create_employee_invite():
    employee = session.get("employee", {})
    actor_role = normalize_role(employee.get("role"))
    if not employee_can_manage_access(employee):
        return jsonify({"ok": False, "error": "Only managers can create dashboard access."}), 403

    biz_id = str(employee.get("biz_id") or "").strip()
    company_name = str(employee.get("company_name") or "").strip()
    body = request.get_json(force=True, silent=True) or {}
    email = normalize_email(body.get("email", ""))
    full_name = str(body.get("full_name") or "").strip()
    role = normalize_role(body.get("role"))

    if not biz_id:
        return jsonify({"ok": False, "error": "This employee account does not have a biz_id."}), 400
    if not full_name:
        return jsonify({"ok": False, "error": "Add the employee's full name."}), 400
    if not email or not is_valid_email(email):
        return jsonify({"ok": False, "error": "Add a valid work email address."}), 400
    if not can_assign_role(actor_role, role):
        return jsonify(
            {"ok": False, "error": "Your role cannot create that level of dashboard access."}
        ), 403

    try:
        existing_profile = fetch_employee_profile_by_email(email)
        if existing_profile and coerce_bool(existing_profile.get("is_active", True)):
            return jsonify(
                {
                    "ok": False,
                    "error": "That email already has an employee dashboard account.",
                }
            ), 409

        rows = supabase_rest_request(
            "POST",
            "employee_invites",
            params={"on_conflict": "email"},
            payload={
                "email": email,
                "biz_id": biz_id,
                "company_name": company_name,
                "full_name": full_name,
                "role": role,
                "is_active": True,
                "invited_by": employee.get("email") or employee.get("full_name") or "manager",
            },
            prefer="resolution=merge-duplicates,return=representation",
        )
    except RuntimeError as exc:
        return jsonify({"ok": False, "error": str(exc)}), 500

    row = rows[0] if isinstance(rows, list) and rows else {}
    return jsonify(
        {
            "ok": True,
            "invite": {
                "email": row.get("email") or email,
                "full_name": row.get("full_name") or full_name,
                "role": normalize_role(row.get("role") or role),
            },
            "register_url": url_for("register", _external=True),
        }
    )


if __name__ == "__main__":
    port = int(os.getenv("PORT", "8600"))
    debug = os.getenv("FLASK_DEBUG", "1") == "1"
    app.run(host="0.0.0.0", port=port, debug=debug)
