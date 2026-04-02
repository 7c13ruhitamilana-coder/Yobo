from __future__ import annotations

import getpass

from werkzeug.security import generate_password_hash


def main() -> None:
    password = getpass.getpass("Password: ").strip()
    if not password:
        raise SystemExit("Password cannot be empty.")
    print(generate_password_hash(password))


if __name__ == "__main__":
    main()
