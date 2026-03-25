from selenium.webdriver.common.by import By
from .base_page import BasePage

class LoginPage(BasePage):
    USERNAME_INPUT = (By.ID, "username")
    PASSWORD_INPUT = (By.ID, "password")
    LOGIN_BUTTON = (By.CSS_SELECTOR, ".login-btn")   # изменено
    ERROR_MESSAGE = (By.ID, "errorMessage")

    def login(self, username, password):
        self.wait_for_element(self.USERNAME_INPUT).send_keys(username)
        self.driver.find_element(*self.PASSWORD_INPUT).send_keys(password)
        self.driver.find_element(*self.LOGIN_BUTTON).click()

    def get_error_text(self):
        return self.wait_for_element(self.ERROR_MESSAGE).text