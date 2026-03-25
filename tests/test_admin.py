from pages.admin_users_page import AdminUsersPage

def test_add_user_via_admin(driver, base_url, db):
    # сначала логинимся как admin
    login_page = LoginPage(driver, f"{base_url}/login.html")
    login_page.open()
    login_page.login("admin", "C-O2kpchop")

    admin_page = AdminUsersPage(driver, f"{base_url}/admin_users.html")
    admin_page.open()
    admin_page.add_user("newadmin", "pass123", "admin@test.com", "admin", True)
    assert db.user_exists("newadmin")
    assert db.get_user_role("newadmin") == "admin"