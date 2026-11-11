#!/usr/bin/env python3
from flask import Flask, jsonify, request
import psycopg2
import psycopg2.extras

app = Flask(__name__)
app.json.ensure_ascii = False
app.json.compact = False
app.json.sort_keys = False

DB_CONFIG = {
    "dbname": "planovac",
    "user": "caloun",
    "password": "hkcn2008",
    "host": "127.0.0.1",
    "client_encoding": "utf8"
}

def get_db_connection():
    return psycopg2.connect(**DB_CONFIG)

@app.route('/', methods=['GET'])
def index():
    return jsonify({
        "service": "Local Attractions API (PostgreSQL Powered)",
        "version": "1.1",
        "description": "Provides a list of monuments, museums, and parks from database.",
        "endpoints": {
            "GET /attractions/date/?city={city}": "Returns attractions for the given city."
        }
    })

@app.route('/attractions/date/', methods=['GET'])
def get_attractions():
    city = request.args.get('city')
    if not city:
        return jsonify({"error": "Missing 'city' parameter. Example: ?city=Praha"}), 400

    try:
        conn = get_db_connection()
        cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)

        query = """
            SELECT a.attraction_code, a.name, a.price_czk, a.price_eur, l.city_name
            FROM attractions a
            JOIN locations l ON a.location_id = l.id
            WHERE l.city_name ILIKE %s;
        """
        cur.execute(query, (city,))
        rows = cur.fetchall()

        filtered = []
        for row in rows:
            filtered.append({
                "id": row["attraction_code"],
                "name": row["name"],
                "location": row["city_name"],
                "type": "outdoor",
                "pricing": {
                    "price_czk": row["price_czk"],
                    "price_eur": float(row["price_eur"]),
                    "is_free": row["price_czk"] == 0
                }
            })

        cur.close()
        conn.close()

        return jsonify({
            "service": "Local Attractions API",
            "version": "1.1",
            "attractions": filtered
        })

    except Exception as e:
        return jsonify({"error": "Databaze neni dostupna", "details": str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8081)
