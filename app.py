from __future__ import annotations

from datetime import date
import socket
import os
from pathlib import Path
import re
from typing import Any

import tomllib
from flask import Flask, abort, jsonify, redirect, render_template, request

try:
    import requests
except Exception:  # pragma: no cover
    requests = None

app = Flask(__name__, template_folder="templates", static_folder="static")


DEMO_FLEET = [
    {
        "id": 1,
        "make": "Ferrari",
        "model": "Roma",
        "color": "Cherry Red",
        "price_per_day": 18500,
        "available": True,
        "photo_url": "https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop",
    },
    {
        "id": 2,
        "make": "Lamborghini",
        "model": "Huracan EVO",
        "color": "Sunset Orange",
        "price_per_day": 21000,
        "available": True,
        "photo_url": "https://images.unsplash.com/photo-1511919884226-fd3cad34687c?q=80&w=1200&auto=format&fit=crop",
    },
    {
        "id": 3,
        "make": "Porsche",
        "model": "911 Carrera",
        "color": "Jet Black",
        "price_per_day": 14000,
        "available": True,
        "photo_url": "https://images.unsplash.com/photo-1544636331-e26879cd4d9b?q=80&w=1200&auto=format&fit=crop",
    },
]

DEMO_INSURANCE = [
    {
        "id": 1,
        "name": "Essential Cover",
        "description": "Basic protection for everyday city driving.",
        "price_per_day": 250,
    },
    {
        "id": 2,
        "name": "Premium Shield",
        "description": "Extended coverage with reduced excess and roadside support.",
        "price_per_day": 450,
    },
    {
        "id": 3,
        "name": "Luxury Guard",
        "description": "Maximum coverage tailored for premium vehicles.",
        "price_per_day": 650,
    },
]

COMPANIES = {
    "demo": {
        "company_name": "Smart Car Rentals",
        "assistant_name": "Yobo",
        "hero_title": "Rent Your Dream Drive in Seconds.",
        "hero_subtitle": "From sleek city sedans to spacious family SUVs, transparent pricing. No hidden fees.",
        "accent": "#ceb7bc",
        "currency": "AED",
        "hero_image": "https://images.unsplash.com/photo-1583121274602-3e2820c69888?q=80&w=1800&auto=format&fit=crop",
        "processing_fee": 50,
        "vat_rate": 0.05,
    }
}


def load_secrets() -> dict[str, Any]:
    path = Path(__file__).with_name("secrets.toml")
    if not path.exists():
        return {}
    try:
        with path.open("rb") as f:
            return tomllib.load(f)
    except Exception:
        return {}


def get_company_config() -> dict[str, Any]:
    company_key = request.args.get("company", "demo").lower()
    base = COMPANIES.get(company_key, COMPANIES["demo"]).copy()
    if request.args.get("company_name"):
        base["company_name"] = request.args.get("company_name")
    if request.args.get("assistant"):
        base["assistant_name"] = request.args.get("assistant")
    if request.args.get("accent"):
        base["accent"] = request.args.get("accent")
    if request.args.get("currency"):
        base["currency"] = request.args.get("currency")
    return base


def get_supabase_config() -> tuple[str | None, str | None]:
    secrets = load_secrets()
    supabase_url = os.getenv("SUPABASE_URL") or secrets.get("SUPABASE_URL")
    supabase_key = os.getenv("SUPABASE_KEY") or secrets.get("SUPABASE_KEY")
    return supabase_url, supabase_key


def get_default_biz_id() -> str:
    secrets = load_secrets()
    return (
        os.getenv("SUPABASE_BIZ_ID")
        or os.getenv("BIZ_ID")
        or os.getenv("biz_id")
        or secrets.get("SUPABASE_BIZ_ID")
        or secrets.get("BIZ_ID")
        or secrets.get("biz_id")
        or ""
    ).strip()


def get_business_lookup_config() -> dict[str, str]:
    secrets = load_secrets()
    return {
        "table": str(
            os.getenv("SUPABASE_BUSINESS_TABLE")
            or secrets.get("SUPABASE_BUSINESS_TABLE")
            or "businesses"
        ).strip(),
        "slug_column": str(
            os.getenv("SUPABASE_BUSINESS_SLUG_COLUMN")
            or secrets.get("SUPABASE_BUSINESS_SLUG_COLUMN")
            or "slug"
        ).strip(),
        "biz_id_column": str(
            os.getenv("SUPABASE_BUSINESS_ID_COLUMN")
            or secrets.get("SUPABASE_BUSINESS_ID_COLUMN")
            or "biz_id"
        ).strip(),
        "name_column": str(
            os.getenv("SUPABASE_BUSINESS_NAME_COLUMN")
            or secrets.get("SUPABASE_BUSINESS_NAME_COLUMN")
            or "name"
        ).strip(),
    }


def fetch_business_by_slug(slug: str) -> dict[str, str] | None:
    slug = (slug or "").strip()
    supabase_url, supabase_key = get_supabase_config()
    if not slug or not supabase_url or not supabase_key or not requests:
        return None

    lookup = get_business_lookup_config()
    endpoint = f"{supabase_url}/rest/v1/{lookup['table']}"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }

    select_fields = [lookup["biz_id_column"], lookup["slug_column"]]

    params = {
        "select": ",".join(select_fields),
        lookup["slug_column"]: f"eq.{slug}",
        "limit": "1",
    }

    try:
        response = requests.get(endpoint, headers=headers, params=params, timeout=10)
        if response.status_code != 200:
            return None
        rows = response.json()
        if not isinstance(rows, list) or not rows:
            return None
        row = rows[0]
        resolved_biz_id = row.get(lookup["biz_id_column"]) or ""
        resolved_slug = row.get(lookup["slug_column"]) or row.get("slug") or slug
        if not resolved_biz_id:
            return None
        return {
            "biz_id": str(resolved_biz_id).strip(),
            "slug": str(resolved_slug).strip(),
            "name": "",
        }
    except Exception:
        return None


def resolve_requested_business(
    slug: str = "", biz_id: str = "", strict_slug: bool = False
) -> tuple[str, str]:
    slug = (slug or "").strip()
    biz_id = (biz_id or "").strip()

    if slug:
        business = fetch_business_by_slug(slug)
        if business and business.get("biz_id"):
            return business["biz_id"], business.get("slug", slug)
        if strict_slug:
            abort(404)

    if biz_id:
        return biz_id, ""

    return get_default_biz_id(), ""


def build_business_page_context(slug: str = "") -> dict[str, str]:
    requested_biz_id = request.args.get("biz_id", "").strip()
    resolved_biz_id, resolved_slug = resolve_requested_business(
        slug=slug, biz_id=requested_biz_id, strict_slug=bool(slug)
    )

    def href(path: str) -> str:
        if resolved_slug:
            if path == "/":
                return f"/b/{resolved_slug}"
            return f"/b/{resolved_slug}{path}"
        if resolved_biz_id:
            joiner = "&" if "?" in path else "?"
            return f"{path}{joiner}biz_id={resolved_biz_id}"
        return path

    return {
        "default_biz_id": resolved_biz_id,
        "default_biz_slug": resolved_slug,
        "home_href": href("/"),
        "details_href": href("/details"),
        "assistant_href": href("/assistant"),
        "assistant_classic_href": href("/assistant-classic"),
        "fleet_href": href("/fleet"),
        "insurance_href": href("/insurance"),
        "quote_href": href("/quote"),
        "location_href": href("/location"),
        "summary_href": href("/summary"),
    }


def redirect_to_assistant(slug: str = ""):
    context = build_business_page_context(slug)
    return redirect(context["assistant_href"])


def get_gemini_config() -> tuple[str | None, str]:
    secrets = load_secrets()
    api_key = os.getenv("GEMINI_API_KEY") or secrets.get("GEMINI_API_KEY")
    model = (
        os.getenv("GEMINI_MODEL")
        or secrets.get("GEMINI_MODEL")
        or "gemini-2.5-flash"
    )
    if isinstance(model, str) and model.startswith("models/"):
        model = model.split("models/", 1)[1]
    return api_key, model


def fetch_fleet_from_supabase(biz_id: str) -> tuple[list[dict[str, Any]], bool, str | None]:
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return DEMO_FLEET, True, "Supabase not configured."

    endpoint = f"{supabase_url}/rest/v1/fleet"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    def attempt(select_fields: str) -> tuple[list[dict[str, Any]] | None, str | None]:
        params = {
            "select": select_fields,
            "available": "eq.true",
        }
        if biz_id:
            params["biz_id"] = f"eq.{biz_id}"

        try:
            response = requests.get(endpoint, headers=headers, params=params, timeout=10)
            if response.status_code == 200:
                data = response.json()
                if not isinstance(data, list):
                    return None, "Unexpected fleet response format."
                return data, None
            return None, f"Supabase response {response.status_code}: {response.text}"
        except Exception as exc:
            return None, f"Supabase error: {exc}"

    extended_select = "id,make,model,price_per_day,available,photo_url,color,luxury"
    luxury_select = "id,make,model,price_per_day,available,photo_url,luxury"
    basic_select = "id,make,model,price_per_day,available,photo_url"

    data, error = attempt(extended_select)
    if data is not None:
        return data, False, None

    luxury_data, luxury_error = attempt(luxury_select)
    if luxury_data is not None:
        return luxury_data, False, None

    fallback_data, fallback_error = attempt(basic_select)
    if fallback_data is not None:
        return fallback_data, False, None

    return [], False, error or luxury_error or fallback_error


def fetch_insurance_from_supabase(biz_id: str) -> list[dict[str, Any]]:
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return DEMO_INSURANCE

    endpoint = f"{supabase_url}/rest/v1/insurance"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    base_params = {
        "select": "id,name,plan_name,title,code,description,details,price,price_per_day,amount",
    }

    def attempt(params: dict[str, Any]) -> list[dict[str, Any]] | None:
        try:
            response = requests.get(endpoint, headers=headers, params=params, timeout=10)
            if response.status_code == 200:
                return response.json()
        except Exception:
            return None
        return None

    params = base_params.copy()
    if biz_id:
        params["biz_id"] = f"eq.{biz_id}"

    data = attempt(params)
    if data is None and biz_id:
        data = attempt(base_params)

    if data is not None:
        return data
    return DEMO_INSURANCE


def fetch_booked_car_ids(
    biz_id: str, start_date: date, end_date: date
) -> set[int | str]:
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return set()

    endpoint = f"{supabase_url}/rest/v1/bookings"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    params = {
        "select": "car_id,start_date,end_date",
        "start_date": f"lte.{end_date.isoformat()}",
        "end_date": f"gte.{start_date.isoformat()}",
    }
    if biz_id:
        params["biz_id"] = f"eq.{biz_id}"

    try:
        response = requests.get(endpoint, headers=headers, params=params, timeout=10)
        if response.status_code == 200:
            rows = response.json()
            return {
                str(row.get("car_id"))
                for row in rows
                if row.get("car_id") is not None
            }
    except Exception:
        return set()
    return set()


def parse_chat_dates(
    text: str, fallback_start: str = "", fallback_end: str = ""
) -> tuple[date | None, date | None]:
    found = re.findall(r"\b\d{4}-\d{2}-\d{2}\b", text)
    start_raw = found[0] if len(found) > 0 else fallback_start
    end_raw = found[1] if len(found) > 1 else fallback_end
    try:
        start_date = date.fromisoformat(start_raw) if start_raw else None
    except ValueError:
        start_date = None
    try:
        end_date = date.fromisoformat(end_raw) if end_raw else None
    except ValueError:
        end_date = None
    return start_date, end_date


def get_available_fleet_for_dates(
    biz_id: str, start_date: date | None, end_date: date | None
) -> list[dict[str, Any]]:
    fleet, _, _ = fetch_fleet_from_supabase(biz_id)
    if start_date and end_date:
        booked_ids = fetch_booked_car_ids(biz_id, start_date, end_date)
        fleet = [car for car in fleet if str(car.get("id")) not in booked_ids]
    return fleet


def format_fleet_lines(fleet: list[dict[str, Any]], limit: int = 8) -> list[str]:
    lines = []
    for item in fleet[:limit]:
        make = item.get("make") or "-"
        model_name = item.get("model") or "-"
        price = item.get("price_per_day") or "-"
        lines.append(f"{make} {model_name} - AED {price}/day")
    return lines


def save_booking_to_supabase(payload: dict[str, Any]) -> tuple[bool, str]:
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return True, "Saved in demo mode (no API yet)."

    allowed_fields = {
        "biz_id",
        "customer_name",
        "phone",
        "total_price",
        "start_date",
        "end_date",
        "city",
        "car_id",
        "location",
        "insurance",
    }
    insert_payload = {
        key: value for key, value in payload.items() if key in allowed_fields
    }

    endpoint = f"{supabase_url}/rest/v1/bookings"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
        "Prefer": "return=representation",
    }

    try:
        response = requests.post(endpoint, headers=headers, json=insert_payload, timeout=10)
        if response.status_code in (200, 201):
            return True, "Booking saved successfully."
        return False, f"Supabase insert failed: {response.text}"
    except Exception as exc:
        return False, f"Supabase error: {exc}"


@app.route("/")
@app.route("/b/<slug>")
def index(slug: str = "") -> str:
    return render_template("index.html", **build_business_page_context(slug))


@app.route("/assistant")
@app.route("/b/<slug>/assistant")
def assistant(slug: str = "") -> str:
    return render_template("assistant_flow.html", **build_business_page_context(slug))


@app.route("/assistant-classic")
@app.route("/b/<slug>/assistant-classic")
def assistant_classic(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/details")
@app.route("/b/<slug>/details")
def details(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/fleet")
@app.route("/b/<slug>/fleet")
def fleet(slug: str = "") -> str:
    return render_template("fleet.html", **build_business_page_context(slug))


@app.route("/quote")
@app.route("/b/<slug>/quote")
def quote(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/insurance")
@app.route("/b/<slug>/insurance")
def insurance(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/location")
@app.route("/b/<slug>/location")
def location(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/summary")
@app.route("/b/<slug>/summary")
def summary(slug: str = "") -> str:
    return redirect_to_assistant(slug)


@app.route("/api/config")
def api_config():
    config = get_company_config()
    biz_id, biz_slug = resolve_requested_business(
        slug=request.args.get("biz_slug", "").strip(),
        biz_id=request.args.get("biz_id", "").strip(),
    )
    config["biz_id"] = biz_id
    config["biz_slug"] = biz_slug
    return jsonify(config)


@app.route("/api/fleet")
def api_fleet():
    biz_id, biz_slug = resolve_requested_business(
        slug=request.args.get("biz_slug", "").strip(),
        biz_id=request.args.get("biz_id", "").strip(),
    )
    start_raw = request.args.get("start_date", "").strip()
    end_raw = request.args.get("end_date", "").strip()
    luxury_raw = request.args.get("luxury", "").strip().lower()
    data, demo, error = fetch_fleet_from_supabase(biz_id)

    try:
        start_date = date.fromisoformat(start_raw) if start_raw else None
        end_date = date.fromisoformat(end_raw) if end_raw else None
    except ValueError:
        start_date = None
        end_date = None

    if start_date and end_date:
        booked_ids = fetch_booked_car_ids(biz_id, start_date, end_date)
        data = [car for car in data if str(car.get("id")) not in booked_ids]

    if luxury_raw and any(car.get("luxury") is not None for car in data):
        def normalize_luxury(value: Any) -> str:
            if isinstance(value, bool):
                return "luxury" if value else "standard"
            lowered = str(value or "").strip().lower()
            if lowered in {"true", "1", "yes", "luxury"}:
                return "luxury"
            if lowered in {"false", "0", "no", "standard"}:
                return "standard"
            return lowered

        if luxury_raw in {"luxury", "standard"}:
            data = [
                car for car in data
                if normalize_luxury(car.get("luxury")) == luxury_raw
            ]

    return jsonify(
        {"ok": True, "data": data, "demo": demo, "error": error, "biz_slug": biz_slug}
    )


@app.route("/api/fleet/debug")
def api_fleet_debug():
    biz_id, biz_slug = resolve_requested_business(
        slug=request.args.get("biz_slug", "").strip(),
        biz_id=request.args.get("biz_id", "").strip(),
    )
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return jsonify(
            {
                "ok": False,
                "error": "Supabase not configured.",
                "biz_id": biz_id,
                "biz_slug": biz_slug,
                "url": supabase_url,
                "has_key": bool(supabase_key),
            }
        )

    endpoint = f"{supabase_url}/rest/v1/fleet"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }
    params = {
        "select": "id,make,model,available,photo_url,biz_id",
        "available": "eq.true",
    }
    if biz_id:
        params["biz_id"] = f"eq.{biz_id}"

    try:
        response = requests.get(endpoint, headers=headers, params=params, timeout=10)
        status = response.status_code
        text = response.text
        return jsonify(
            {
                "ok": status == 200,
                "status": status,
                "biz_id": biz_id,
                "biz_slug": biz_slug,
                "url": supabase_url,
                "params": params,
                "body": text,
            }
        )
    except Exception as exc:
        return jsonify(
            {
                "ok": False,
                "error": f"Supabase error: {exc}",
                "biz_id": biz_id,
                "biz_slug": biz_slug,
                "url": supabase_url,
                "params": params,
            }
        )


@app.route("/api/debug/runtime")
def api_debug_runtime():
    secrets_path = Path(__file__).with_name("secrets.toml")
    secrets = load_secrets()
    supabase_url, supabase_key = get_supabase_config()
    biz_id = get_default_biz_id()

    host = ""
    host_resolution = {"ok": False, "error": "No SUPABASE_URL configured."}
    if supabase_url:
        host = re.sub(r"^https?://", "", supabase_url).split("/", 1)[0]
        try:
            resolved = socket.gethostbyname(host)
            host_resolution = {"ok": True, "host": host, "ip": resolved}
        except OSError as exc:
            host_resolution = {"ok": False, "host": host, "error": str(exc)}

    return jsonify(
        {
            "cwd": os.getcwd(),
            "app_file": str(Path(__file__).resolve()),
            "port_env": os.getenv("PORT"),
            "flask_debug_env": os.getenv("FLASK_DEBUG"),
            "secrets_path": str(secrets_path),
            "secrets_file_exists": secrets_path.exists(),
            "secrets_keys": sorted(secrets.keys()),
            "supabase_url_present": bool(supabase_url),
            "supabase_url_host": host,
            "supabase_key_present": bool(supabase_key),
            "supabase_key_prefix": str(supabase_key or "")[:12],
            "supabase_biz_id": biz_id,
            "host_resolution": host_resolution,
        }
    )


@app.route("/api/debug/business")
def api_debug_business():
    requested_slug = request.args.get("biz_slug", "").strip()
    requested_biz_id = request.args.get("biz_id", "").strip()
    lookup = get_business_lookup_config()
    supabase_url, supabase_key = get_supabase_config()

    payload: dict[str, Any] = {
        "requested_slug": requested_slug,
        "requested_biz_id": requested_biz_id,
        "lookup_config": lookup,
        "supabase_url_present": bool(supabase_url),
        "supabase_key_present": bool(supabase_key),
        "resolved": None,
        "query": None,
        "status": None,
        "body": None,
        "error": None,
    }

    if not requested_slug:
        payload["error"] = "Missing biz_slug query parameter."
        return jsonify(payload), 400

    if not supabase_url or not supabase_key or not requests:
        payload["error"] = "Supabase is not configured on this runtime."
        return jsonify(payload), 500

    endpoint = f"{supabase_url}/rest/v1/{lookup['table']}"
    headers = {
        "apikey": supabase_key,
        "Authorization": f"Bearer {supabase_key}",
        "Content-Type": "application/json",
    }

    select_fields = [lookup["biz_id_column"], lookup["slug_column"]]

    params = {
        "select": ",".join(select_fields),
        lookup["slug_column"]: f"eq.{requested_slug}",
        "limit": "1",
    }
    payload["query"] = {"endpoint": endpoint, "params": params}

    try:
        response = requests.get(endpoint, headers=headers, params=params, timeout=10)
        payload["status"] = response.status_code
        payload["body"] = response.text
        if response.status_code == 200:
            payload["resolved"] = fetch_business_by_slug(requested_slug)
        else:
            payload["error"] = f"Supabase response {response.status_code}"
    except Exception as exc:
        payload["error"] = str(exc)

    return jsonify(payload)


@app.route("/api/estimate", methods=["POST"])
def api_estimate():
    body = request.get_json(force=True, silent=True) or {}
    start_raw = body.get("start_date")
    end_raw = body.get("end_date")
    price_per_day = int(body.get("price_per_day", 0))

    try:
        start_date = date.fromisoformat(start_raw)
        end_date = date.fromisoformat(end_raw)
    except Exception:
        return jsonify({"ok": False, "error": "Invalid date format. Use YYYY-MM-DD."}), 400

    days = max((end_date - start_date).days, 1)
    total = max(price_per_day, 0) * days
    return jsonify({"ok": True, "days": days, "estimated_total": total})


@app.route("/api/insurance")
def api_insurance():
    biz_id, biz_slug = resolve_requested_business(
        slug=request.args.get("biz_slug", "").strip(),
        biz_id=request.args.get("biz_id", "").strip(),
    )
    data = fetch_insurance_from_supabase(biz_id)
    return jsonify(
        {"ok": True, "data": data, "demo": data == DEMO_INSURANCE, "biz_slug": biz_slug}
    )


@app.route("/api/chat", methods=["POST"])
def api_chat():
    body = request.get_json(force=True, silent=True) or {}
    message = (body.get("message") or "").strip()
    if not message:
        return jsonify({"ok": False, "error": "Message is required."}), 400

    history = body.get("history") or []
    biz_id, biz_slug = resolve_requested_business(
        slug=(body.get("business_slug") or "").strip(),
        biz_id=(body.get("biz_id") or "").strip(),
    )
    context = body.get("context") or {}
    context_start = (context.get("start_date") or "").strip()
    context_end = (context.get("end_date") or "").strip()
    context_city = (context.get("city") or "").strip()

    start_date, end_date = parse_chat_dates(message, context_start, context_end)
    available_fleet = get_available_fleet_for_dates(biz_id, start_date, end_date)
    available_lines = format_fleet_lines(available_fleet)

    api_key, model = get_gemini_config()
    if not api_key or not requests:
        return jsonify(
            {
                "ok": False,
                "error": "Gemini API key not configured.",
                "reply": "I'm not connected yet. Please try again later.",
            }
        )

    system_text = (
        "You are Yobo, the Smart Car Rentals assistant. "
        "Help customers pick cars based on their needs and budget. "
        "Only recommend cars from the fleet list below. "
        "If asked about color, say color info is not available. "
        "If the customer asks to browse the fleet, tell them you can open the fleet page. "
        "Ask for pickup location and dates if missing. "
        "Keep responses brief and helpful.\n\n"
        f"Selected city: {context_city or 'Not provided'}\n"
        f"Selected start date: {start_date.isoformat() if start_date else 'Not provided'}\n"
        f"Selected end date: {end_date.isoformat() if end_date else 'Not provided'}\n"
        "Available fleet for the selected dates:\n- "
        + ("\n- ".join(available_lines) if available_lines else "No cars available for those dates.")
    )

    contents: list[dict[str, Any]] = []
    for entry in history[-6:]:
        role = "user" if entry.get("role") == "user" else "model"
        text = entry.get("content") or ""
        if text:
            contents.append({"role": role, "parts": [{"text": text}]})
    contents.append({"role": "user", "parts": [{"text": message}]})

    payload = {
        "contents": contents,
        "system_instruction": {"parts": [{"text": system_text}]},
        "generationConfig": {"temperature": 0.6, "maxOutputTokens": 400},
    }

    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
    headers = {"x-goog-api-key": api_key, "Content-Type": "application/json"}
    try:
        response = requests.post(url, headers=headers, json=payload, timeout=15)
        if response.status_code != 200:
            return jsonify(
                {
                    "ok": False,
                    "error": f"Gemini response {response.status_code}: {response.text}",
                    "reply": f"Gemini error {response.status_code}. Check API key/model.",
                }
            )
        data = response.json()
        candidates = data.get("candidates", [])
        if not candidates:
            return jsonify({"ok": False, "error": "No response.", "reply": ""})
        parts = candidates[0].get("content", {}).get("parts", [])
        reply = " ".join(part.get("text", "") for part in parts).strip()
        lowered = message.lower()
        fleet_action = None
        if any(
            phrase in lowered
            for phrase in (
                "fleet",
                "available cars",
                "available fleet",
                "show cars",
                "show fleet",
                "browse cars",
                "browse fleet",
            )
        ):
            from urllib.parse import urlencode

            query = {}
            if biz_slug:
                href = f"/b/{biz_slug}/fleet"
            else:
                href = "/fleet"
            if biz_id and not biz_slug:
                query["biz_id"] = biz_id
            if start_date:
                query["start_date"] = start_date.isoformat()
            if end_date:
                query["end_date"] = end_date.isoformat()
            if context_city:
                query["city"] = context_city
            if query:
                href = f"/fleet?{urlencode(query)}"
            if biz_slug:
                href = f"/b/{biz_slug}/fleet"
                if start_date or end_date or context_city:
                    slug_query = {}
                    if start_date:
                        slug_query["start_date"] = start_date.isoformat()
                    if end_date:
                        slug_query["end_date"] = end_date.isoformat()
                    if context_city:
                        slug_query["city"] = context_city
                    if slug_query:
                        href = f"/b/{biz_slug}/fleet?{urlencode(slug_query)}"
            fleet_action = {
                "label": "View Available Fleet" if start_date and end_date else "Go To Fleet",
                "href": href,
            }

        if fleet_action and start_date and end_date:
            if available_lines:
                availability_prefix = (
                    f"Available from {start_date.isoformat()} to {end_date.isoformat()}: "
                    + "; ".join(available_lines[:5])
                    + ". "
                )
            else:
                availability_prefix = (
                    f"No cars are currently available from {start_date.isoformat()} "
                    f"to {end_date.isoformat()}. "
                )
            reply = availability_prefix + reply

        return jsonify(
            {
                "ok": True,
                "reply": reply,
                "action": fleet_action,
                "context": {
                    "start_date": start_date.isoformat() if start_date else "",
                    "end_date": end_date.isoformat() if end_date else "",
                },
            }
        )
    except Exception as exc:
        return jsonify({"ok": False, "error": f"Gemini error: {exc}", "reply": ""})


@app.route("/api/bookings", methods=["POST"])
def api_bookings():
    body = request.get_json(force=True, silent=True) or {}
    if not body.get("biz_id"):
        body["biz_id"], _ = resolve_requested_business(
            slug=(body.get("business_slug") or "").strip(),
            biz_id="",
        )
    if not body.get("insurance"):
        body["insurance"] = "No insurance"
    required = [
        "biz_id",
        "customer_name",
        "phone",
        "city",
        "start_date",
        "end_date",
        "car_id",
        "total_price",
        "location",
        "insurance",
    ]
    missing = [field for field in required if not body.get(field)]
    if missing:
        return jsonify({"ok": False, "error": f"Missing fields: {', '.join(missing)}"}), 400

    try:
        body["total_price"] = int(round(float(body.get("total_price", 0))))
    except (TypeError, ValueError):
        return jsonify({"ok": False, "error": "Invalid total_price format."}), 400

    try:
        start_date = date.fromisoformat(str(body.get("start_date")))
        end_date = date.fromisoformat(str(body.get("end_date")))
    except (TypeError, ValueError):
        return jsonify({"ok": False, "error": "Invalid booking dates."}), 400

    today = date.today()
    if start_date < today:
        return jsonify({"ok": False, "error": "Start date cannot be in the past."}), 400
    if end_date < start_date:
        return jsonify({"ok": False, "error": "End date cannot be before start date."}), 400

    ok, message = save_booking_to_supabase(body)
    if ok:
        return jsonify({"ok": True, "message": message})
    return jsonify({"ok": False, "error": message}), 500


if __name__ == "__main__":
    port = int(os.getenv("PORT", "8504"))
    debug = os.getenv("FLASK_DEBUG", "1") == "1"
    app.run(host="0.0.0.0", port=port, debug=debug)
