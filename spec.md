# Specyfikacja aplikacji Cost Split

# Wymagania funkcjonalne

1. Administrator loguje się tokenem.
1. Administrator rejestruje grupy, osoby w grupach i wysyła linki dostępowe.
1. Administrator określa w grupie udział do zwrotu w procentach, który może być inny dla wybranych osób.
1. Osoby wprowadzają zakupy w PLN.
1. Osoby widzą listę zakupów.
1. Osoby widzą listę kwot do zwrotu.
1. Osoby potwierdzają kwotę, którą otrzymały.

### Reguły biznesowe

Reguły muszą być zapewnione po stronie serwera. W przypadku naruszenia reguły użytkownik widzi komunikat z błędem walidacji.

1. Suma udziałów jest równa 100%.

### Przykłady widoków

#### Dodaję zakup

- Nazwa
- Kwota

#### Zakupy

- Kto kupił
- Co kupił
- Kwota

#### Do zwrotu

- Kto zwraca
- Dla kogo zwrot
- Kwota zwrócona
- Pozostało do zwrotu

#### Potwierdzam odbiór

- Od kogo - wybierz osobę
- Kwota

## Wymagania niefunkcjonalne

1. PHP 8.5.
