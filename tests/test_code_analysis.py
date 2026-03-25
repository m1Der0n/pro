import os
import pytest
from utils.halstead_analyzer import analyze_all_php_files

def test_halstead_metrics():
    # Поднимаемся на два уровня вверх (из tests/ в корень проекта) и добавляем путь к сайту
    # Предполагаем, что структура: Miller123/tests/ и Miller123/../s953066i.beget.tech/public_html
    site_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "s953066i.beget.tech", "public_html"))
    
    # Если директория не существует, пропускаем тест
    if not os.path.isdir(site_root):
        pytest.skip(f"Директория {site_root} не найдена")
    
    metrics = analyze_all_php_files(site_root)
    assert len(metrics) > 0, "Не найдено ни одного PHP-файла"
    for m in metrics:
        assert m['V'] >= 0