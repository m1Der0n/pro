import pymysql

class DBHelper:
    def __init__(self, config):
        self.config = config
        self.connection = None
        self.connect()

    def connect(self):
        try:
            self.connection = pymysql.connect(**self.config)
        except Exception as e:
            print(f"Ошибка подключения к БД: {e}")
            self.connection = None

    def user_exists(self, username):
        if not self.connection:
            return False
        try:
            with self.connection.cursor() as cursor:
                cursor.execute("SELECT COUNT(*) FROM users WHERE username = %s", (username,))
                return cursor.fetchone()[0] > 0
        except Exception as e:
            print(f"Ошибка проверки пользователя: {e}")
            return False

    def get_user_role(self, username):
        if not self.connection:
            return None
        try:
            with self.connection.cursor() as cursor:
                cursor.execute("SELECT role FROM users WHERE username = %s", (username,))
                result = cursor.fetchone()
                return result[0] if result else None
        except Exception as e:
            print(f"Ошибка получения роли: {e}")
            return None

    def delete_user(self, username):
        """Удаляет пользователя (для очистки после тестов)"""
        if not self.connection:
            return False
        try:
            with self.connection.cursor() as cursor:
                cursor.execute("DELETE FROM users WHERE username = %s AND username != 'admin'", (username,))
                self.connection.commit()
                return cursor.rowcount > 0
        except Exception as e:
            print(f"Ошибка удаления пользователя: {e}")
            return False

    def close(self):
        if self.connection:
            self.connection.close()