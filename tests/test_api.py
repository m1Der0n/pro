import requests

def test_api_validity(base_url):
    headers = {
        'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'application/json'
    }
    response = requests.get(f"{base_url}/api.php", headers=headers)
    assert response.status_code == 200
    data = response.json()
    assert isinstance(data, list)