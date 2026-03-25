from selenium.webdriver.common.by import By
from .base_page import BasePage

class RegisterPage(BasePage):
    USERNAME_INPUT = (By.ID, "username")
    PASSWORD_INPUT = (By.ID, "password")
    EMAIL_INPUT = (By.ID, "email")
    REGISTER_BUTTON = (By.CSS_SELECTOR, "button[type='submit']")
    SUCCESS_MESSAGE = (By.CLASS_NAME, "info-message")
    ERROR_MESSAGE = (By.ID, "errorMessage")

    def register(self, username, password, email=None):
        self.wait_for_element(self.USERNAME_INPUT).send_keys(username)
        self.driver.find_element(*self.PASSWORD_INPUT).send_keys(password)
        if email:
            self.driver.find_element(*self.EMAIL_INPUT).send_keys(email)
        self.driver.find_element(*self.REGISTER_BUTTON).click()

    def get_success_message(self):
        try:
            return self.wait_for_element(self.SUCCESS_MESSAGE).text
        except:
            return ""

    def get_error_message(self):
        try:
            return self.wait_for_element(self.ERROR_MESSAGE).text
        except:
            return ""