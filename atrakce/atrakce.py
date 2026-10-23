#!/usr/bin/env python3
from flask import Flask, jsonify, request

app = Flask(__name__)
app.json.ensure_ascii = False
app.json.compact = False
app.json.sort_keys = False

attractions_data = [
    {
      "id": "ATTR-PRG-01", "name": "Karlov most", "location": "Praha", "type": "outdoor",
      "pricing": {"price_czk": 0, "price_eur": 0.0, "is_free": True}
    },
    {
      "id": "ATTR-PRG-02", "name": "Národní muzeum", "location": "Praha", "type": "indoor",
      "pricing": {"price_czk": 350, "price_eur": 14.0, "is_free": False}
    },
    {
      "id": "ATTR-PRG-03", "name": "Pražský hrad (Okruh)", "location": "Praha", "type": "outdoor",
      "pricing": {"price_czk": 450, "price_eur": 18.0, "is_free": False}
    },
    {
      "id": "ATTR-BRN-01", "name": "Hrad Špilberk", "location": "Brno", "type": "outdoor",
      "pricing": {"price_czk": 200, "price_eur": 8.0, "is_free": False}
    },
    {
      "id": "ATTR-BRN-02", "name": "Vila Tugendhat", "location": "Brno", "type": "indoor",
      "pricing": {"price_czk": 400, "price_eur": 16.0, "is_free": False}
    },
    {
      "id": "ATTR-KRU-01", "name": "Státní hrad a zámek Český Krumlov", "location": "Český Krumlov", "type": "outdoor",
      "pricing": {"price_czk": 300, "price_eur": 12.0, "is_free": False}
    },
    {
      "id": "ATTR-KRU-02", "name": "Otáčivé hlediště", "location": "Český Krumlov", "type": "outdoor",
      "pricing": {"price_czk": 1000, "price_eur": 40.0, "is_free": False}
    },
    {
      "id": "ATTR-SPM-01", "name": "Skiareál Svatý Petr", "location": "Špindlerův Mlýn", "type": "outdoor",
      "pricing": {"price_czk": 1200, "price_eur": 48.0, "is_free": False}
    },
    {
      "id": "ATTR-SPM-02", "name": "Pramen Labe", "location": "Špindlerův Mlýn", "type": "outdoor",
      "pricing": {"price_czk": 0, "price_eur": 0.0, "is_free": True}
    }
]

@app.route('/', methods=['GET'])
def index():
    return jsonify({
        "service": "Local Attractions API",
        "version": "1.0",
        "description": "Provides a list of monuments, museums, and parks for a specified city.",
        "endpoints": {
            "GET /attractions/date/?city={city}": "Returns attractions for the given city."
        }
    })

@app.route('/attractions/date/', methods=['GET'])
def get_attractions():
    city = request.args.get('city')

    if not city:
        return jsonify({"error": "Missing 'city' parameter. Example: ?city=Praha"}), 400

    filtered = [a for a in attractions_data if a["location"].lower() == city.lower()]

    return jsonify({
        "service": "Local Attractions API",
        "version": "1.0",
        "attractions": filtered
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8081)
