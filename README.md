# Cost Split

Aplikacja do rozliczania wspólnych zakupów w grupie (specyfikacja: [spec.md](spec.md)).
PHP 8.5, MySQL 5.7, JavaScript – bez frameworków, bez Composera i bez `mod_rewrite`.

## Wdrożenie na hostingu (FTP + SQL)

1. Utwórz bazę MySQL w panelu hostingu i zaimportuj do niej [db/init.sql](db/init.sql) (np. phpMyAdmin → Import).
2. Skopiuj `app/config.example.php` jako `app/config.php` i uzupełnij dane bazy oraz `admin_token`
   (długi, losowy ciąg znaków).
3. Wgraj **zawartość** katalogu `app/` przez FTP, np. do `public_html/` lub `public_html/koszty/`.
4. Otwórz w przeglądarce adres aplikacji i zaloguj się tokenem administratora.

Aktualizacja istniejącej bazy: zaimportuj kolejno pliki `db/migrate-*.sql`, których jeszcze nie wykonano.

Aplikacja nie wymaga `.htaccess` – wszystkie strony obsługuje `index.php?r=...`.
Pliki w `src/` i `templates/` nic nie wyświetlają po bezpośrednim wywołaniu.

## Uruchomienie lokalne (Docker Compose)

```sh
ADMIN_TOKEN=sekret docker compose up -d --build
```

Aplikacja: http://localhost:8080 (port zmienisz zmienną `APP_PORT`).

## Testy

```sh
node --test tests/
docker compose run --rm --no-deps app php /var/www/tests/run.php
```

## Zasady rozliczeń

- Udział osoby w grupie można wpisać ręcznie. Osoby bez wpisanego udziału dzielą po równo resztę do 100%
  (np. 3 osoby: 33,34% / 33,33% / 33,33%).
- Suma udziałów musi wynosić 100% – sprawdzane w JavaScript i na serwerze.
- Każda osoba pokrywa swój udział w sumie zakupów. Z różnicy między tym, co zapłaciła, a tym, co powinna pokryć,
  powstaje saldo osoby; plan zwrotów wyrównuje salda najmniejszą liczbą przelewów.
- Stan rozliczeń jest wyliczany przy każdym wyświetleniu, więc edycja lub usunięcie zakupu, zwrotu czy osoby
  od razu zmienia „Do zwrotu”.
- Zwrot zgłoszony przez dłużnika jest **Niepotwierdzony**; zmniejsza „Pozostało do zwrotu” dopiero, gdy odbiorca
  go potwierdzi (**Potwierdzony**). Odbiorca może też od razu wpisać potwierdzony odbiór.
- Administrator wpisuje osoby, dla których kupowane są prezenty. Nie biorą one udziału w rozliczeniu, ale każdy
  zakup musi wskazywać jedną z nich (sprawdzane w JavaScript i na serwerze). Osoby, która ma prezenty, nie można
  usunąć. Podsumowanie „Prezenty dla osób” pokazuje łączną kwotę prezentów dla każdej osoby.
- Administrator zmienia i usuwa wszystko. Osoba zmienia swoje zakupy, zwroty, które otrzymała, i swoje
  niepotwierdzone zgłoszenia.
