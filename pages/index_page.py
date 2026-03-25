from selenium.webdriver.common.by import By
from .base_page import BasePage

class IndexPage(BasePage):
    # Можно добавить методы для проверки главной страницы
    def is_logged_in(self):
        return "Выйти" in self.driver.page_source