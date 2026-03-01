DROP TABLE IF EXISTS comment;
DROP TABLE IF EXISTS article;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS country;

CREATE TABLE country (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  details TEXT NOT NULL
);

INSERT INTO country (id, name, details) VALUES
(1, 'Slovakia', '{"name": "Slovensko", "pop": 5456300, "gdp": 90.75}'),
(2, 'Canada', '{"name": "Canada", "pop": 37198400, "gdp": 1592.37}'),
(3, 'Germany', '{"name": "Deutschland", "pop": 82385700, "gdp": 3486.12}');

CREATE TABLE user (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  country_id INTEGER NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('admin', 'author')),
  name TEXT NOT NULL
);

INSERT INTO user (id, country_id, type, name) VALUES
(1, 1, 'admin', 'Marek'),
(2, 1, 'author', 'Robert'),
(3, 2, 'admin', 'Chris'),
(4, 2, 'author', 'Kevin');

CREATE TABLE article (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  published_at TEXT NOT NULL,
  title TEXT NOT NULL,
  content TEXT NOT NULL
);

INSERT INTO article (id, user_id, published_at, title, content) VALUES
(1, 1, '2011-12-10 12:10:00', 'article 1', 'content 1'),
(2, 2, '2011-12-20 16:20:00', 'article 2', 'content 2'),
(3, 1, '2012-01-04 22:00:00', 'article 3', 'content 3'),
(4, 4, '2018-07-07 15:15:07', 'artïcle 4', 'content 4'),
(5, 3, '2018-10-01 01:10:01', 'article 5', 'content 5'),
(6, 3, '2019-01-21 07:00:00', 'სარედაქციო 6', '함유량 6');

CREATE TABLE comment (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  content TEXT NOT NULL
);

INSERT INTO comment (id, article_id, user_id, content) VALUES
(1, 1, 1, 'comment 1.1'),
(2, 1, 2, 'comment 1.2'),
(3, 2, 1, 'comment 2.1'),
(4, 5, 4, 'cömment 5.4'),
(5, 6, 2, 'ਟਿੱਪਣੀ 6.2');