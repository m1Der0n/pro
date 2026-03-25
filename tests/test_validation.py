from pages.index_page import IndexPage
from utils.db_helper import DBHelper

def test_rating_consistency(driver, base_url, db):
    driver.get(f"{base_url}/index.html")
    index_page = IndexPage(driver)
    players_ui = index_page.get_players_names_and_scores()
    # получаем данные из БД
    players_db = db.get_all_players()
    # сравниваем
    assert len(players_ui) == len(players_db)
    # можно проверить, что рейтинги совпадают
    for ui in players_ui:
        db_player = next(p for p in players_db if p['player_name'] == ui['name'])
        assert abs(ui['score'] - db_player['total_rating']) < 0.01