from __future__ import annotations

from datetime import date
import socket
import os
from pathlib import Path
import re
from typing import Any

import tomllib
from flask import Flask, jsonify, redirect, render_template, request

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

DEFAULT_COMPANY = {
    "company_name": "Veep",
    "brand_wordmark": "VEEP",
    "assistant_name": "Yobo",
    "hero_title": "Find Your Next Drive With Veep.",
    "hero_subtitle": "Browse the fleet, build a quick SGD estimate, and submit your interest with Yobo.",
    "accent": "#f59a3c",
    "currency": "SGD",
    "hero_image": "https://images.unsplash.com/photo-1583121274602-3e2820c69888?q=80&w=1800&auto=format&fit=crop",
    "processing_fee": 50,
    "vat_rate": 0.09,
    "biz_id": "",
    "slug": "",
    "dashboard_provider": "",
    "dashboard_url": "",
    "dashboard_label": "",
    "dashboard_company_name": "",
    "dashboard_branch": "",
    "dashboard_reference": "",
    "flow": {},
}


def _normalize_hex_color(value: str, fallback: str) -> str:
    candidate = str(value or "").strip().lstrip("#")
    if len(candidate) == 3 and re.fullmatch(r"[0-9a-fA-F]{3}", candidate):
        candidate = "".join(ch * 2 for ch in candidate)
    if not re.fullmatch(r"[0-9a-fA-F]{6}", candidate):
        candidate = fallback.lstrip("#")
    return f"#{candidate.lower()}"


def _mix_hex_color(color: str, target: str, ratio: float) -> str:
    ratio = max(0.0, min(1.0, ratio))
    source = _normalize_hex_color(color, "#f59a3c").lstrip("#")
    blend = _normalize_hex_color(target, "#ffffff").lstrip("#")
    channels = []
    for index in (0, 2, 4):
        source_value = int(source[index:index + 2], 16)
        blend_value = int(blend[index:index + 2], 16)
        mixed = round(source_value * (1 - ratio) + blend_value * ratio)
        channels.append(f"{mixed:02x}")
    return f"#{''.join(channels)}"


def build_theme_vars(accent: str) -> dict[str, str]:
    resolved = _normalize_hex_color(accent, DEFAULT_COMPANY["accent"])
    rgb = [str(int(resolved[index:index + 2], 16)) for index in (1, 3, 5)]
    return {
        "theme_accent": _mix_hex_color(resolved, "#ffffff", 0.72),
        "theme_accent_deep": resolved,
        "theme_accent_rgb": ", ".join(rgb),
        "theme_border": _mix_hex_color(resolved, "#ffffff", 0.82),
        "theme_bg": _mix_hex_color(resolved, "#ffffff", 0.95),
        "theme_soft": _mix_hex_color(resolved, "#ffffff", 0.88),
    }


def build_location_routing_config() -> dict[str, Any]:
    businesses, _ = load_business_profiles()
    explicit_slug = bool(request.view_args and request.view_args.get("slug"))
    explicit_biz = bool(request.args.get("biz_id", "").strip())
    forced_default_slug = str(os.getenv("DEFAULT_BUSINESS_SLUG") or "").strip().lower()
    enabled = not explicit_slug and not explicit_biz and not forced_default_slug and len(businesses) > 1

    market_defaults = {
        "SGD": {
            "market_city": "Singapore",
            "market_country_codes": ["SG"],
            "market_timezones": ["Asia/Singapore"],
        },
        "AED": {
            "market_city": "Dubai",
            "market_country_codes": ["AE"],
            "market_timezones": ["Asia/Dubai"],
        },
    }

    targets: list[dict[str, Any]] = []
    for slug, config in businesses.items():
        currency = str(config.get("currency") or "").strip().upper()
        fallback = market_defaults.get(currency, {})
        country_codes = config.get("market_country_codes") or fallback.get("market_country_codes") or []
        timezones = config.get("market_timezones") or fallback.get("market_timezones") or []
        market_city = str(config.get("market_city") or fallback.get("market_city") or "").strip()
        targets.append(
            {
                "slug": slug,
                "currency": currency,
                "market_city": market_city,
                "country_codes": [str(code).strip().upper() for code in country_codes if str(code).strip()],
                "timezones": [str(tz).strip() for tz in timezones if str(tz).strip()],
            }
        )

    return {
        "enabled": enabled,
        "targets": targets,
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


def load_business_profiles() -> tuple[dict[str, dict[str, Any]], str]:
    fallback = {"veep": DEFAULT_COMPANY.copy()}
    fallback["veep"]["slug"] = "veep"
    fallback["veep"]["biz_id"] = get_default_biz_id()

    path = Path(__file__).with_name("businesses.toml")
    if not path.exists():
        return fallback, "veep"

    try:
        with path.open("rb") as f:
            raw = tomllib.load(f)
    except Exception:
        return fallback, "veep"

    raw_businesses = raw.get("businesses")
    if not isinstance(raw_businesses, dict) or not raw_businesses:
        return fallback, "veep"

    businesses: dict[str, dict[str, Any]] = {}
    for raw_slug, config in raw_businesses.items():
        if not isinstance(config, dict):
            continue
        slug = str(raw_slug).strip().lower()
        if not slug:
            continue
        profile = DEFAULT_COMPANY.copy()
        profile.update(config)
        profile["slug"] = slug
        profile["biz_id"] = str(profile.get("biz_id") or "").strip()
        profile["brand_wordmark"] = str(
            profile.get("brand_wordmark") or profile.get("company_name") or slug
        ).strip()
        businesses[slug] = profile

    if not businesses:
        return fallback, "veep"

    default_slug = str(
        os.getenv("DEFAULT_BUSINESS_SLUG")
        or raw.get("default_slug")
        or next(iter(businesses))
    ).strip().lower()
    if default_slug not in businesses:
        default_slug = next(iter(businesses))
    return businesses, default_slug


def get_company_config() -> dict[str, Any]:
    businesses, default_slug = load_business_profiles()
    slug = ""
    if request.view_args:
        slug = str(request.view_args.get("slug") or "").strip().lower()
    biz_id = request.args.get("biz_id", "").strip() or get_default_biz_id()

    profile: dict[str, Any] | None = None
    if slug:
        profile = businesses.get(slug)
    if profile is None and biz_id:
        profile = next(
            (
                item
                for item in businesses.values()
                if str(item.get("biz_id") or "").strip() == biz_id
            ),
            None,
        )
    if profile is None:
        profile = businesses.get(default_slug, DEFAULT_COMPANY.copy())

    base = DEFAULT_COMPANY.copy()
    base.update(profile)
    base["slug"] = str(base.get("slug") or slug or default_slug or "").strip().lower()
    base["biz_id"] = str(base.get("biz_id") or biz_id or get_default_biz_id()).strip()
    if request.args.get("company_name"):
        base["company_name"] = request.args.get("company_name")
    if request.args.get("assistant"):
        base["assistant_name"] = request.args.get("assistant")
    if request.args.get("accent"):
        base["accent"] = request.args.get("accent")
    if request.args.get("currency"):
        base["currency"] = request.args.get("currency")
    return base


def get_dashboard_config() -> dict[str, str]:
    config = get_company_config()
    return {
        "provider": str(config.get("dashboard_provider") or "").strip(),
        "url": str(config.get("dashboard_url") or "").strip(),
        "label": str(config.get("dashboard_label") or "").strip(),
        "company_name": str(config.get("dashboard_company_name") or "").strip(),
        "branch": str(config.get("dashboard_branch") or "").strip(),
        "reference": str(config.get("dashboard_reference") or "").strip(),
    }


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


def requested_biz_id() -> str:
    return (
        request.args.get("biz_id", "").strip()
        or str(get_company_config().get("biz_id") or "").strip()
        or get_default_biz_id()
    )


def build_business_page_context() -> dict[str, Any]:
    config = get_company_config()
    dashboard = get_dashboard_config()
    routing = build_location_routing_config()
    resolved_biz_id = str(config.get("biz_id") or "").strip()
    resolved_slug = str(config.get("slug") or "").strip()
    theme = build_theme_vars(str(config.get("accent") or DEFAULT_COMPANY["accent"]))
    use_generic_paths = bool(routing.get("enabled"))

    def href(path: str) -> str:
        if use_generic_paths:
            return path
        if resolved_slug:
            if path == "/":
                return f"/b/{resolved_slug}/"
            return f"/b/{resolved_slug}{path}"
        if resolved_biz_id:
            joiner = "&" if "?" in path else "?"
            return f"{path}{joiner}biz_id={resolved_biz_id}"
        return path

    return {
        "default_biz_id": resolved_biz_id,
        "current_business_slug": resolved_slug,
        "company_name": str(config.get("company_name") or DEFAULT_COMPANY["company_name"]),
        "brand_wordmark": str(config.get("brand_wordmark") or config.get("company_name") or ""),
        "assistant_name": str(config.get("assistant_name") or DEFAULT_COMPANY["assistant_name"]),
        "hero_title": str(config.get("hero_title") or DEFAULT_COMPANY["hero_title"]),
        "hero_subtitle": str(config.get("hero_subtitle") or DEFAULT_COMPANY["hero_subtitle"]),
        "accent": str(config.get("accent") or DEFAULT_COMPANY["accent"]),
        "currency": str(config.get("currency") or DEFAULT_COMPANY["currency"]),
        "processing_fee": str(config.get("processing_fee") or DEFAULT_COMPANY["processing_fee"]),
        "vat_rate": str(config.get("vat_rate") or DEFAULT_COMPANY["vat_rate"]),
        **theme,
        "has_dashboard_config": "true" if dashboard.get("url") else "false",
        "dashboard_provider": dashboard.get("provider", ""),
        "assistant_flow_config": config.get("flow") if isinstance(config.get("flow"), dict) else {},
        "location_routing_config": routing,
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


def redirect_to_assistant():
    context = build_business_page_context()
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
    if not biz_id:
        return [], False, "Missing biz_id."

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

    branch_select = (
        "id,make,model,price_per_day,available,photo_url,color,luxury,"
        "city,branch_city,branch_location,location"
    )
    extended_select = "id,make,model,price_per_day,available,photo_url,color,luxury"
    luxury_select = "id,make,model,price_per_day,available,photo_url,luxury"
    basic_select = "id,make,model,price_per_day,available,photo_url"

    data, error = attempt(branch_select)
    if data is not None:
        return data, False, None

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
    if not biz_id:
        return []
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
        lines.append(f"{make} {model_name} - SGD {price}/day")
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
def index() -> str:
    return render_template("index.html", **build_business_page_context())


@app.route("/b/<slug>/")
def business_index(slug: str) -> str:
    return render_template("index.html", **build_business_page_context())


@app.route("/assistant")
def assistant() -> str:
    return render_template("assistant_flow.html", **build_business_page_context())


@app.route("/b/<slug>/assistant")
def business_assistant(slug: str) -> str:
    return render_template("assistant_flow.html", **build_business_page_context())


@app.route("/assistant-classic")
def assistant_classic() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/assistant-classic")
def business_assistant_classic(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/details")
def details() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/details")
def business_details(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/fleet")
def fleet() -> str:
    return render_template("fleet.html", **build_business_page_context())


@app.route("/b/<slug>/fleet")
def business_fleet(slug: str) -> str:
    return render_template("fleet.html", **build_business_page_context())


@app.route("/quote")
def quote() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/quote")
def business_quote(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/insurance")
def insurance() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/insurance")
def business_insurance(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/location")
def location() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/location")
def business_location(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/summary")
def summary() -> str:
    return redirect_to_assistant()


@app.route("/b/<slug>/summary")
def business_summary(slug: str) -> str:
    return redirect_to_assistant()


@app.route("/api/config")
def api_config():
    config = get_company_config()
    public_config = {
        "company_name": config.get("company_name"),
        "brand_wordmark": config.get("brand_wordmark"),
        "assistant_name": config.get("assistant_name"),
        "hero_title": config.get("hero_title"),
        "hero_subtitle": config.get("hero_subtitle"),
        "accent": config.get("accent"),
        "currency": config.get("currency"),
        "hero_image": config.get("hero_image"),
        "processing_fee": config.get("processing_fee"),
        "vat_rate": config.get("vat_rate"),
        "slug": config.get("slug"),
        "biz_id": requested_biz_id(),
    }
    return jsonify(public_config)


@app.route("/api/fleet")
def api_fleet():
    biz_id = requested_biz_id()
    if not biz_id:
        return jsonify({"ok": False, "data": [], "demo": False, "error": "Missing biz_id."}), 400

    start_raw = request.args.get("start_date", "").strip()
    end_raw = request.args.get("end_date", "").strip()
    city_raw = request.args.get("city", "").strip().lower()
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

    if city_raw:
        def normalize_city(value: Any) -> str:
            return re.sub(r"[^a-z0-9]+", "", str(value or "").strip().lower())

        city_fields = ("city", "branch_city", "branch_location", "location")
        city_matches = [
            car for car in data
            if any(car.get(field) for field in city_fields)
        ]
        if city_matches:
            normalized_requested = normalize_city(city_raw)
            data = [
                car for car in data
                if any(
                    normalize_city(car.get(field)) == normalized_requested
                    for field in city_fields
                    if car.get(field)
                )
            ]

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

    return jsonify({"ok": True, "data": data, "demo": demo, "error": error})


@app.route("/api/fleet/debug")
def api_fleet_debug():
    biz_id = requested_biz_id()
    supabase_url, supabase_key = get_supabase_config()
    if not supabase_url or not supabase_key or not requests:
        return jsonify(
            {
                "ok": False,
                "error": "Supabase not configured.",
                "biz_id": biz_id,
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
    biz_id = requested_biz_id()
    data = fetch_insurance_from_supabase(biz_id)
    return jsonify({"ok": True, "data": data, "demo": data == DEMO_INSURANCE})


@app.route("/api/chat", methods=["POST"])
def api_chat():
    body = request.get_json(force=True, silent=True) or {}
    message = (body.get("message") or "").strip()
    if not message:
        return jsonify({"ok": False, "error": "Message is required."}), 400

    history = body.get("history") or []
    biz_id = (body.get("biz_id") or "").strip() or get_default_biz_id()
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
        "You are Yobo, the Veep assistant. "
        "Help customers pick cars based on their needs and budget. "
        "Only recommend cars from the fleet list below. "
        "If asked about color, say color info is not available. "
        "If the customer asks to browse the fleet, tell them you can open the fleet page. "
        "Ask for the rental dates if missing. "
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
            href = "/fleet"
            if biz_id:
                query["biz_id"] = biz_id
            if start_date:
                query["start_date"] = start_date.isoformat()
            if end_date:
                query["end_date"] = end_date.isoformat()
            if context_city:
                query["city"] = context_city
            if query:
                href = f"/fleet?{urlencode(query)}"
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
        body["biz_id"] = get_default_biz_id()
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

    booked_ids = fetch_booked_car_ids(str(body.get("biz_id")), start_date, end_date)
    if str(body.get("car_id")) in booked_ids:
        return jsonify(
            {
                "ok": False,
                "error": "That vehicle is no longer available for the selected dates.",
            }
        ), 409

    ok, message = save_booking_to_supabase(body)
    if ok:
        return jsonify({"ok": True, "message": message})
    return jsonify({"ok": False, "error": message}), 500


if __name__ == "__main__":
    port = int(os.getenv("PORT", "8504"))
    debug = os.getenv("FLASK_DEBUG", "1") == "1"
    app.run(host="0.0.0.0", port=port, debug=debug)
