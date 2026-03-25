import pytest
import uuid
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from pages.login_page import LoginPage
from pages.register_page import RegisterPage
from pages.index_page import IndexPage

def test_login_success(driver, base_url):
    login_page = LoginPage(driver, f"{base_url}/login.html")
    login_page.open()
    login_page.login("admin", "C-O2kpchop")
    WebDriverWait(driver, 10).until(EC.url_contains("index.html"))
    assert "Mafia Рейтинг" in driver.page_source or "MAFIA РЕЙТИНГ" in driver.page_source

def test_login_invalid(driver, base_url):
    login_page = LoginPage(driver, f"{base_url}/login.html")
    login_page.open()
    login_page.login("wrong", "wrong")
    assert "Неверный логин или пароль" in login_page.get_error_text()

def test_register_success(driver, base_url, db):
    # Генерируем уникальное имя пользователя, чтобы не было конфликтов
    unique_username = f"testuser_{uuid.uuid4().hex[:8]}"
    password = "testpass123"
    email = f"{unique_username}@test.com"
    
    # Регистрируем нового пользователя
    register_page = RegisterPage(driver, f"{base_url}/register.html")
    register_page.open()
    register_page.register(unique_username, password, email)
    
    # Проверяем, что появилось сообщение об успешной регистрации
    # (на странице регистрации может быть info-message)
    success_msg = register_page.get_success_message()
    # Или перенаправление на страницу входа с сообщением
    WebDriverWait(driver, 10).until(EC.url_contains("login.html"))
    
    # Проверяем через БД, что пользователь создан
    assert db.user_exists(unique_username), f"Пользователь {unique_username} не найден в БД"
    assert db.get_user_role(unique_username) == "guest", "Роль должна быть guest"
    
    # Проверяем, что можно войти с созданными данными
    login_page = LoginPage(driver, f"{base_url}/login.html")
    login_page.open()
    login_page.login(unique_username, password)
    WebDriverWait(driver, 10).until(EC.url_contains("index.html"))
    assert "Mafia Рейтинг" in driver.page_source
    
    # Очистка: удаляем тестового пользователя из БД
    db.delete_user(unique_username)

def test_register_duplicate(driver, base_url, db):
    """Проверка, что нельзя зарегистрировать существующего пользователя"""
    username = "admin"  # существующий пользователь
    password = "testpass"
    
    register_page = RegisterPage(driver, f"{base_url}/register.html")
    register_page.open()
    register_page.register(username, password)
    
    # Должна появиться ошибка
    error_msg = register_page.get_error_message()
    assert "существует" in error_msg or "уже" in error_msg