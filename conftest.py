import pytest
from selenium import webdriver
from selenium.webdriver.firefox.service import Service
from webdriver_manager.firefox import GeckoDriverManager
from utils.db_helper import DBHelper
from config import BASE_URL, DB_CONFIG
from pathlib import Path
from datetime import datetime
from weasyprint import HTML

@pytest.fixture(scope="session")
def base_url():
    return BASE_URL

@pytest.fixture(scope="function")
def driver(base_url):
    options = webdriver.FirefoxOptions()
    # options.add_argument("--headless")
    driver = webdriver.Firefox(service=Service(GeckoDriverManager().install()), options=options)
    driver.get(base_url)
    yield driver
    driver.quit()

@pytest.fixture(scope="session")
def db():
    try:
        db = DBHelper(DB_CONFIG)
        yield db
        db.close()
    except Exception as e:
        pytest.skip(f"База данных недоступна: {e}")

def pytest_configure(config):
    # Указываем, что HTML-отчёт будет сохраняться
    config.option.htmlpath = "test_report.html"
    
    # Добавляем метаданные в отчёт
    config._metadata = {
        'Тестовая среда': 'RedOS 8.0.2',
        'Браузер': 'Firefox',
        'Сайт': BASE_URL,
        'Время запуска': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        'Python': '3.11.11',
        'Pytest': '9.0.2'
    }

def pytest_html_report_title(report):
    """Изменяем заголовок отчёта"""
    report.title = "Mafia Rating - Полный отчёт тестирования"

def pytest_html_results_table_header(cells):
    """Добавляем дополнительные колонки в таблицу результатов"""
    # Добавляем новые колонки после существующих
    cells.insert(2, '<th>Описание теста</th>')
    cells.insert(3, '<th>Время выполнения (с)</th>')
    cells.insert(4, '<th>Детали ошибки</th>')

def pytest_html_results_table_row(report, cells):
    """Заполняем дополнительные колонки"""
    # Описание теста
    test_name = report.nodeid.split('::')[-1] if '::' in report.nodeid else report.nodeid
    cells.insert(2, f'<td>{test_name}</td>')
    
    # Время выполнения
    duration = getattr(report, 'duration', 0)
    cells.insert(3, f'<td>{duration:.2f}</td>')
    
    # Детали ошибки (если тест упал)
    if report.failed:
        error_msg = str(report.longrepr) if report.longrepr else "Неизвестная ошибка"
        # Обрезаем длинные сообщения для читаемости
        if len(error_msg) > 500:
            error_msg = error_msg[:500] + "..."
        cells.insert(4, f'<td class="error-details">{error_msg}</td>')
    else:
        cells.insert(4, '<td>-</td>')

def pytest_html_results_table_html(report, data):
    """Добавляем иконки и улучшаем отображение результатов"""
    if report.passed:
        data.clear()
        data.append('<div class="passed">✅ Тест пройден успешно</div>')
    elif report.failed:
        data.clear()
        data.append('<div class="failed">❌ Тест не пройден</div>')
    elif report.skipped:
        data.clear()
        data.append('<div class="skipped">⏭️ Тест пропущен</div>')

def pytest_sessionfinish(session, exitstatus):
    """Конвертируем HTML в PDF после завершения тестов"""
    html_path = Path("test_report.html")
    if html_path.exists():
        report_dir = Path("reports")
        report_dir.mkdir(exist_ok=True)
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = report_dir / f"test_report_{timestamp}.pdf"
        
        try:
            HTML(filename=str(html_path)).write_pdf(str(pdf_path))
            print(f"\n{'='*60}")
            print(f"✅ PDF-отчёт сохранён: {pdf_path}")
            print(f"{'='*60}")
            print(f"📊 Статистика тестирования:")
            print(f"   Всего тестов: {session.testscollected}")
            print(f"   ✅ Пройдено: {session.testspassed}")
            print(f"   ❌ Не пройдено: {session.testsfailed}")
            print(f"   ⏭️  Пропущено: {session.testsskipped}")
            print(f"   ⏱️  Общее время: {getattr(session, 'duration', 0):.2f} сек")
            print(f"{'='*60}")
        except Exception as e:
            print(f"\n❌ Ошибка создания PDF: {e}")
            print("💡 HTML-отчёт сохранён как test_report.html")