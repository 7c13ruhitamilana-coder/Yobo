# AI Booking Plugin: Beginner Setup (PyCharm)

## 1) Open the project
1. Open PyCharm.
2. Click **Open**.
3. Select this folder: `/Users/nisha/Documents/Playground`.

## 2) Create Python environment
1. Go to **PyCharm > Settings > Project > Python Interpreter**.
2. Click **Add Interpreter**.
3. Choose **Virtualenv**.
4. Click **OK**.

## 3) Install packages
Open PyCharm Terminal and run:

```bash
cd /Users/nisha/Documents/Playground
pip install -r requirements.txt
```

## 4) Run the app
In the same terminal run:

```bash
streamlit run app.py
```

If that fails, run:

```bash
python -m streamlit run app.py
```

Then open the link shown in terminal (usually `http://localhost:8501`).

## 5) Connect Supabase
Create folder/file:
- `.streamlit/secrets.toml`

Put this inside:

```toml
SUPABASE_URL="https://YOUR_PROJECT.supabase.co"
SUPABASE_KEY="YOUR_SERVICE_OR_ANON_KEY"
```

Restart app after saving.

## 6) Use it as plugin for each company
Pass values in the URL.

### Minimum format
```text
http://localhost:8501/?company=demo&biz_id=BUSINESS_UUID
```

### Another company style
```text
http://localhost:8501/?company=luxride&biz_id=BUSINESS_UUID
```

### Full custom override
```text
http://localhost:8501/?company=demo&biz_id=BUSINESS_UUID&company_name=CityRide&assistant=Nova&accent=%23d7b8be&currency_symbol=$
```

## 7) What client must provide
1. Their `biz_id` UUID.
2. Fleet rows in `public.fleet` with that `biz_id`.
3. Booking table `public.bookings`.
4. Your hosted app link.

## 8) What happens in flow
1. Greeting UI appears.
2. Customer fills booking details.
3. App fetches fleet by `biz_id`.
4. Customer picks car.
5. App calculates `days * price_per_day`.
6. Confirm booking inserts into `bookings`.

## 9) Sell-ready checklist
1. Add your logo/company profile in `COMPANY_PROFILES` in `app.py`.
2. Host Streamlit app (Streamlit Cloud, Render, EC2, etc.).
3. Give each company a URL with their `biz_id`.
4. Test booking insert before going live.
