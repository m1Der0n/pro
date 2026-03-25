import threading
import time
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

def worker(thread_id, base_url):
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.get(f"{base_url}/login.html")
    # выполнить несколько действий
    # ...
    driver.quit()


def test_load():
    threads = []
    for i in range(5):  # 5 одновременных пользователей
        t = threading.Thread(target=worker, args=(i, "http://localhost"))
        threads.append(t)
        t.start()
    for t in threads:
        t.join()