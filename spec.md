# Specyfikacja aplikacji Cost Split

Zrzutka na prezenty.

# Wymagania funkcjonalne

1. Administrator loguje się tokenem.
1. Administrator rejestruje grupy, osoby w grupach i wysyła linki dostępowe.
1. Administrator określa w grupie udział do zwrotu w procentach, który może być inny dla wybranych osób.
1. Administrator rejestruje osoby, dla których kupowane są prezenty.
1. Osoby wprowadzają zakupy w PLN.
1. Osoby widzą listę zakupów.
1. Osoby widzą listę kwot do zwrotu.
1. Osoby potwierdzają kwotę, którą otrzymały.
1. Osoby zgłaszają zwrot, który wysłały – ma status Niepotwierdzony, dopóki odbiorca nie potwierdzi odbioru.
1. Zwroty na liście mają status Potwierdzony lub Niepotwierdzony; do rozliczenia liczą się tylko potwierdzone.
1. Dane i zdarzenia można zmieniać i usuwać: administrator – grupy, osoby, zakupy i zwroty; osoba – swoje zakupy,
   zwroty, które otrzymała, oraz niepotwierdzone zwroty, które zgłosiła.
1. Prezentowany stan rozliczeń jest wyliczany na bieżąco z aktualnych danych.
1. Plan zwrotów minimalizuje liczbę przelewów potrzebnych do rozliczenia osób.
1. Osoby widzą podsumowanie z łączną kwotą wydana na prezenty dla poszczególnych osób.

### Reguły biznesowe

Reguły muszą być wspierane po stronie klienta i zapewnione po stronie serwera. W przypadku naruszenia reguły użytkownik widzi komunikat z błędem walidacji.

1. Suma udziałów jest równa 100% (także po usunięciu osoby).
1. Każdy zakup wskazuje osobę, dla której jest prezent (nie można usunąć osoby, która ma prezenty).

### Przykłady widoków

#### Dodaję zakup

- Nazwa
- Kwota
- Dla kogo prezent

#### Zakupy

- Kto kupił
- Co kupił
- Kwota

#### Do zwrotu

- Kto zwraca
- Dla kogo zwrot
- Kwota zwrócona
- Pozostało do zwrotu

#### Zwroty

- Od kogo
- Dla kogo
- Kwota
- Status: Potwierdzony, Niepotwierdzony

#### Potwierdzam odbiór

- Od kogo - wybierz osobę
- Kwota

#### Zgłaszam zwrot

- Dla kogo - wybierz osobę
- Kwota

## Wymagania niefunkcjonalne

1. PHP 8.5.
1. MySQL 5.7.
1. JavaScript.
1. Uruchomienie Docker Compose.
1. Dostosowanie wyglądu do małej szerokości okna.
