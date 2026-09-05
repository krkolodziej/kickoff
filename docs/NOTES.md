# Notatki implementacyjne

Zapis tego, **dlaczego** kod wygląda tak, jak wygląda. Odnośniki `plik:linia` prowadzą do
miejsca, w którym decyzja jest zrealizowana.

Aplikacja jest przepisaniem wcześniejszego projektu z Django + DRF na Symfony + React.
Sensem ćwiczenia nie jest port linia po linii, tylko **mapowanie pojęć** — tabela na końcu
dokumentu rośnie z każdym etapem.

---

## Spis treści

- [Stage 1 — fundament: konta, JWT, koperta błędów](#stage-1--fundament-konta-jwt-koperta-błędów)
  - [1.1 Szkielet, a nie webapp](#11-szkielet-a-nie-webapp)
  - [1.2 Trzy firewalle](#12-trzy-firewalle)
  - [1.3 Dwa tokeny i dwa różne miejsca przechowywania](#13-dwa-tokeny-i-dwa-różne-miejsca-przechowywania)
  - [1.4 Koperta błędów](#14-koperta-błędów)
  - [1.5 DTO, nie encja](#15-dto-nie-encja)
  - [1.6 Klient API po stronie Reacta](#16-klient-api-po-stronie-reacta)
  - [1.7 Tailwind v4 i dark mode](#17-tailwind-v4-i-dark-mode)
  - [1.8 Testy](#18-testy)
  - [1.9 Pułapki, na które realnie wpadłem](#19-pułapki-na-które-realnie-wpadłem)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-1)
- [Stage 2 — organizacje, role per-org, scope resolver](#stage-2--organizacje-role-per-org-scope-resolver)
  - [2.1 Rola nie jest rolą Symfony](#21-rola-nie-jest-rolą-symfony)
  - [2.2 Scope i ValueResolver](#22-scope-i-valueresolver)
  - [2.3 404 zamiast 403](#23-404-zamiast-403)
  - [2.4 Voter, który nie odpytuje bazy](#24-voter-który-nie-odpytuje-bazy)
  - [2.5 Niezmienniki właściciela](#25-niezmienniki-właściciela)
  - [2.6 Slug: sufiks zamiast odmowy](#26-slug-sufiks-zamiast-odmowy)
  - [2.7 Pułapki Stage 2](#27-pułapki-stage-2)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-2)
- [Stage 3a — ligi, kluby, zawodnicy i maszyneria list](#stage-3a--ligi-kluby-zawodnicy-i-maszyneria-list)
  - [3a.1 Klub nie należy do ligi](#3a1-klub-nie-należy-do-ligi)
  - [3a.2 ListQuery i MapQueryString](#3a2-listquery-i-mapquerystring)
  - [3a.3 Paginacja opt-in](#3a3-paginacja-opt-in)
  - [3a.4 Sortowanie: whitelista i remis](#3a4-sortowanie-whitelista-i-remis)
  - [3a.5 Slug per organizacja](#3a5-slug-per-organizacja)
  - [3a.6 URL jako stan listy](#3a6-url-jako-stan-listy)
  - [3a.7 Pułapki Stage 3a](#3a7-pułapki-stage-3a)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-3a)
- [Stage 3b — sezony, rejestracja klubów i składy](#stage-3b--sezony-rejestracja-klubów-i-składy)
  - [3b.1 Własny Constraint: kiedy naprawdę jest potrzebny](#3b1-własny-constraint-kiedy-naprawdę-jest-potrzebny)
  - [3b.2 Gdzie mieszka która reguła](#3b2-gdzie-mieszka-która-reguła)
  - [3b.3 NULL-e w unikalnym indeksie](#3b3-nulle-w-unikalnym-indeksie)
  - [3b.4 Kapitan: degradacja zamiast odmowy](#3b4-kapitan-degradacja-zamiast-odmowy)
  - [3b.5 Cztery poziomy zagnieżdżenia](#3b5-cztery-poziomy-zagnieżdżenia)
  - [3b.6 Dwa N+1 w jednym endpointcie](#3b6-dwa-n1-w-jednym-endpointcie)
  - [3b.7 Data to nie moment](#3b7-data-to-nie-moment)
  - [3b.8 Pułapki Stage 3b](#3b8-pułapki-stage-3b)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-3b)
- [Stage 4 — generowanie terminarza](#stage-4--generowanie-terminarza)
  - [4.1 Algorytm bez frameworka](#41-algorytm-bez-frameworka)
  - [4.2 Metoda okręgowa](#42-metoda-okręgowa)
  - [4.3 Błąd, który znalazł test](#43-błąd-który-znalazł-test)
  - [4.4 Blokada wiersza, nie sprawdzenie](#44-blokada-wiersza-nie-sprawdzenie)
  - [4.5 Determinizm](#45-determinizm)
  - [4.6 Pułapki Stage 4](#46-pułapki-stage-4)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-4)
- [Stage 5 — mecze, maszyna stanów i zdarzenia](#stage-5--mecze-maszyna-stanów-i-zdarzenia)
  - [5.1 Dlaczego nie ma encji Match](#51-dlaczego-nie-ma-encji-match)
  - [5.2 Maszyna stanów jako dane](#52-maszyna-stanów-jako-dane)
  - [5.3 Dlaczego nie symfony/workflow](#53-dlaczego-nie-symfonyworkflow)
  - [5.4 Gol i wynik w jednej transakcji](#54-gol-i-wynik-w-jednej-transakcji)
  - [5.5 409 kontra 422](#55-409-kontra-422)
  - [5.6 Zegar z wstrzyknięcia](#56-zegar-z-wstrzyknięcia)
  - [5.7 Serwer mówi, co wolno](#57-serwer-mówi-co-wolno)
  - [5.8 Pułapki Stage 5](#58-pułapki-stage-5)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-5)
- [Stage 6 — tabela, statystyki i dane demonstracyjne](#stage-6--tabela-statystyki-i-dane-demonstracyjne)
  - [6.1 Tabela nie jest przechowywana](#61-tabela-nie-jest-przechowywana)
  - [6.2 Dlaczego dwa zapytania, a nie jedno](#62-dlaczego-dwa-zapytania-a-nie-jedno)
  - [6.3 Remisy trzeba rozstrzygnąć do końca](#63-remisy-trzeba-rozstrzygnąć-do-końca)
  - [6.4 Tabela czeka na gwizdek, lista strzelców nie](#64-tabela-czeka-na-gwizdek-lista-strzelców-nie)
  - [6.5 Czego w statystykach nie ma](#65-czego-w-statystykach-nie-ma)
  - [6.6 Seeder gra przez prawdziwe serwisy](#66-seeder-gra-przez-prawdziwe-serwisy)
  - [6.7 Determinizm i idempotencja](#67-determinizm-i-idempotencja)
  - [6.8 Pułapki Stage 6](#68-pułapki-stage-6)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-6)
- [Stage 7 — Messenger, powiadomienia i harmonogram](#stage-7--messenger-powiadomienia-i-harmonogram)
  - [7.1 Transport w bazie, i po co](#71-transport-w-bazie-i-po-co)
  - [7.2 Wiadomość niesie identyfikator](#72-wiadomość-niesie-identyfikator)
  - [7.3 „Co najmniej raz" i klucz deduplikacji](#73-co-najmniej-raz-i-klucz-deduplikacji)
  - [7.4 Handler musi znieść, że świat się zmienił](#74-handler-musi-znieść-że-świat-się-zmienił)
  - [7.5 Harmonogram w kodzie, nie w cronie](#75-harmonogram-w-kodzie-nie-w-cronie)
  - [7.6 Worker: Windows i produkcja](#76-worker-windows-i-produkcja)
  - [7.7 Pułapki Stage 7](#77-pułapki-stage-7)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-7)
- [Stage 8 — Realtime i hardening](#stage-8--realtime-i-hardening)
  - [8.1 Hub w tym samym procesie](#81-hub-w-tym-samym-procesie)
  - [8.2 Cienkie zdarzenia](#82-cienkie-zdarzenia)
  - [8.3 Token na jeden temat, w ciasteczku](#83-token-na-jeden-temat-w-ciasteczku)
  - [8.4 Publikacja przez kolejkę](#84-publikacja-przez-kolejkę)
  - [8.5 Degradacja jest funkcją, nie awarią](#85-degradacja-jest-funkcją-nie-awarią)
  - [8.6 Limit prób logowania](#86-limit-prób-logowania)
  - [8.7 Żądania warunkowe](#87-żądania-warunkowe)
  - [8.8 PHPStan 6 → 8](#88-phpstan-6--8)
  - [8.9 Pułapki Stage 8](#89-pułapki-stage-8)
  - [Pytania na rozmowę](#pytania-na-rozmowę--stage-8)
- [Wdrożenie](#wdrożenie)
  - [D.1 Dlaczego PostgreSQL](#d1-dlaczego-postgresql)
  - [D.2 Co znalazła zmiana bazy](#d2-co-znalazła-zmiana-bazy)
  - [D.3 Jeden obraz, jeden origin](#d3-jeden-obraz-jeden-origin)
  - [D.4 Klucze JWT nie mogą powstawać przy starcie](#d4-klucze-jwt-nie-mogą-powstawać-przy-starcie)
  - [D.5 Migracje z entrypointu](#d5-migracje-z-entrypointu)
  - [D.6 Deploy dopiero po zielonych checkach](#d6-deploy-dopiero-po-zielonych-checkach)
  - [D.7 Jeden menedżer pakietów](#d7-jeden-menedżer-pakietów)
  - [D.8 Wersja serwera to konfiguracja](#d8-wersja-serwera-to-konfiguracja)
  - [D.9 Connection string nie jest przenośny](#d9-connection-string-nie-jest-przenośny)
  - [D.10 Pierwszy deploy: dwie usterki](#d10-pierwszy-deploy-dwie-usterki)
  - [Pytania na rozmowę](#pytania-na-rozmowę--wdrożenie)
- [Django → Symfony](#django--symfony)

---

## Stage 1 — fundament: konta, JWT, koperta błędów

### 1.1 Szkielet, a nie webapp

`symfony new backend --version=7.4` daje **skeleton**, nie `--webapp`. Różnica: webapp
dociąga Twiga, Encore/AssetMapper, formularze HTML, mailera. Tu API oddaje JSON i nic
więcej, więc każdy z tych bundli byłby kodem, który trzeba utrzymywać bez powodu.

Twig i tak wchodzi — ale wyłącznie jako zależność `web-profiler-bundle`, czyli w `dev` i
`test`. Widać to w `backend/config/bundles.php`.

**Flex i recepty.** `composer require` uruchamia *receptę* pakietu: dopisuje bundle do
`bundles.php`, tworzy plik w `config/packages/`, dokłada zmienne do `.env`, wpisy do
`.gitignore`. To nie magia — recepty leżą publicznie w repo `symfony/recipes`.

Dwie rzeczy, które warto wiedzieć:

- `composer require --no-scripts` **wyłącza recepty**, bo Flex podpina się pod zdarzenia
  Composera, które są skryptami. Brakujące recepty nadrabia się później:
  `composer recipes` pokazuje status, `composer recipes:install <pakiet> --force` je dogrywa.
- Recepty z repozytorium `symfony/recipes-contrib` (społecznościowe, mniej weryfikowane) są
  domyślnie **ignorowane** — pokazuje się `IGNORING` przy instalacji. Włącza je
  `extra.symfony.allow-contrib: true` w `composer.json`. Bez tego
  `gesdinet/jwt-refresh-token-bundle` instaluje się bez konfiguracji i bez wpisu w
  `bundles.php`, a aplikacja przestaje widzieć jego trasy.

Przydatne polecenia diagnostyczne:

```bash
php bin/console debug:autowiring Interface   # co jest wstrzykiwalne pod danym typem
php bin/console debug:container --parameters # parametry kontenera
php bin/console lint:container               # czy typy w kontenerze się zgadzają
php bin/console debug:router                 # tablica tras
```

### 1.2 Trzy firewalle

`backend/config/packages/security.yaml`

Odruch mówi „jeden firewall na całe `^/api`". Tutaj są trzy i to jest celowe: **firewall to
nie jest strefa URL-i, tylko sposób uwierzytelniania**. Każde z trzech żądań przedstawia
zupełnie inne poświadczenie:

| Firewall | Wzorzec | Poświadczenie | Authenticator |
| --- | --- | --- | --- |
| `login` | `^/api/v1/auth/login$` | e-mail + hasło w ciele żądania | `json_login` |
| `refresh` | `^/api/v1/token/refresh$` | ciasteczko `refresh_token` | `refresh_jwt` (Gesdinet) |
| `api` | `^/api` | nagłówek `Authorization: Bearer` | `jwt` (Lexik) |

Kolejność ma znaczenie: Symfony bierze **pierwszy** firewall, którego `pattern` pasuje.
Firewall to nie „poziom ochrony", tylko **wybór authenticatora** — nie ma mocniejszych i
słabszych, są takie, które czytają hasło z ciała żądania, i takie, które czytają nagłówek.

Sprawdzone przez zamianę `api` i `login` miejscami: poprawne dane logowania dają wtedy
**500**, nie 401. Łańcuch jest taki — firewall `api` przejmuje żądanie, authenticator `jwt`
nie znajduje nagłówka `Authorization` i się nie aktywuje, `access_control` przepuszcza (bo
`^/api/v1/auth/(login|register)$` ma `PUBLIC_ACCESS`), więc żądanie **dociera do
kontrolera**, a ten rzuca `LogicException`. Logowanie przestaje istnieć jako mechanizm.

Stąd `throw` zamiast pustej metody: gdyby `login()` zwracało `new JsonResponse([])`, ta sama
literówka dawałaby **200 z pustym ciałem** i szukałoby się jej we froncie.

**`stateless: true`** znaczy: żadnej sesji, żadnego ciasteczka `PHPSESSID`, żadnego
`_security_main` w sesji. Konsekwencja, o którą pyta się na rozmowach: **skoro nie ma
sesyjnego ciasteczka, które przeglądarka dołącza automatycznie, to nie ma też czego
podpiąć pod atak CSRF** — dlatego przy `json_login` na stateless firewallu nie konfiguruje
się tokenu CSRF. To nie zaniedbanie, tylko wniosek z braku ciasteczka sesyjnego.

Uwaga do `check_path: api_auth_login`. Trasa **musi istnieć**, mimo że kontroler nigdy się
nie wykona. Powód jest czysto kolejnościowy — `php bin/console debug:event-dispatcher
kernel.request` pokazuje:

```
#7   RouterListener::onKernelRequest()      32
#11  FirewallListener::onKernelRequest()     8
```

Routing biegnie **przed** firewallem, więc bez trasy `RouterListener` rzuca 404 zanim
`json_login` zajrzy do ciała żądania (sprawdzone: zakomentowanie `#[Route]` daje
`{"detail":"Not found.","code":"not_found"}` przy nietkniętej konfiguracji security).
Kontroler nie wykonuje się dlatego, że authenticator na priorytecie 8 ustawia odpowiedź i
przerywa propagację — do `kernel.controller` nigdy nie dochodzi. Stąd metoda, która tylko
rzuca wyjątek: `backend/src/Controller/Api/AuthController.php:41`.

Drobiazg wart zapamiętania: `check_path` przyjmuje **nazwę trasy** albo ścieżkę. Przy nazwie
usunięcie trasy wywala już budowanie kontenera — kolejny dowód, że to realna infrastruktura,
a nie ozdobnik.

**Passport.** Warto umieć nazwać przepływ: authenticator tworzy `Passport` z *badge'ami* —
`UserBadge` (kogo szukamy) i `PasswordCredentials` albo `CustomCredentials`. Provider
znajduje użytkownika, `PasswordHasher` weryfikuje hasło, a potem powstaje `TokenInterface`
w `TokenStorage`. Voter (Stage 2) dostaje właśnie ten token.

### 1.3 Dwa tokeny i dwa różne miejsca przechowywania

To najważniejsza decyzja tego etapu i najczęstsze pytanie rekrutacyjne o JWT.

**Access token** — `backend/config/packages/lexik_jwt_authentication.yaml`, TTL 900 s.
Podpisany RS256 kluczem prywatnym z `config/jwt/`. Jest *bearer*: kto go ma, ten jest
zalogowany. Nie da się go unieważnić — to podpis nad zestawem roszczeń, nie wiersz w bazie,
którą można skasować. **Jedyne, co ogranicza skradziony access token, to zegar** — stąd
15 minut, a nie 24 godziny.

**Refresh token** — `backend/config/packages/gesdinet_jwt_refresh_token.yaml`, TTL 30 dni.
Żyje w bazie (`refresh_tokens`, encja `backend/src/Entity/RefreshToken.php`), więc **da się
go unieważnić** i właśnie to robi wylogowanie:
`backend/src/Controller/Api/AuthController.php:86`.

Gdzie co leży:

- Access token: **wyłącznie zmienna modułowa w JS** (`frontend/src/api/client.ts:51`).
  Nigdy `localStorage`. Uzasadnienie do wypowiedzenia na głos: co może przeczytać skrypt,
  to może przeczytać też skrypt wstrzyknięty przez XSS, a poświadczenie w storage przeżywa
  zamknięcie karty.
- Refresh token: **ciasteczko httpOnly** o ścieżce `/api/v1/token`. JavaScript go nie widzi,
  a przeglądarka dołącza je do dokładnie jednego endpointu.

Kompromis, który trzeba umieć nazwać: to jest odporne na kradzież tokenu przez XSS, ale w
zamian **tworzy powierzchnię CSRF na endpointcie refresh**. Łagodzą ją: `SameSite=Lax`,
metoda tylko POST i wąska ścieżka ciasteczka.

Dodatkowo `hash_tokens.enabled: true` — w kolumnie `refresh_token` leży *hash* wartości,
którą dostała przeglądarka. Wyciek dumpu bazy nie daje działających tokenów.

`single_use` (rotacja) jest sterowane zmienną `JWT_REFRESH_SINGLE_USE` i **wyłączone w
dev** — powód w sekcji 1.9.

### 1.4 Koperta błędów

`backend/src/EventSubscriber/ApiExceptionSubscriber.php`

Kontrakt jest jeden dla całego `/api`:

```json
{ "detail": "...", "code": "...", "fields": { "pole": ["komunikat"] } }
```

Subscriber siedzi na `kernel.exception` z priorytetem 0 — czyli **po** listenerze firewalla
(priorytet 1), który zamienia `AuthenticationException` na wywołanie entry pointu. Jeśli
tamten już ustawił odpowiedź, subscriber wychodzi od razu (`:45`).

Trzy rzeczy, które kosztowały mnie czas:

1. **`#[MapRequestPayload]` rzuca dwa różne wyjątki**, oba opakowane w `HttpException`:
   `ValidationFailedException` (422, wartości są złe) i `PartialDenormalizationException`
   (400, ciała nie dało się w ogóle zdeserializować). Trzeba je *rozpakować* z
   `getPrevious()` — inaczej niepoprawny JSON ucieka jako gołe 400 z komunikatem Symfony
   (`:67`).
2. **Ścieżki właściwości są camelCase.** Violation na `$passwordConfirm` ma
   `propertyPath === 'passwordConfirm'`, a cała reszta API mówi snake_case. To nie jest
   kwestia bezpieczeństwa — to zwykłe niedopasowanie dwóch stringów, ale skutek zależy od
   klienta i w gorszym wariancie jest paskudny:

   - W naszym kliencie `applyApiErrorToForm` porównuje klucz z listą pól formularza. Nieznany
     klucz trafia do kubełka „nieprzypisane" i wyświetla się jako **ogólny baner** nad
     formularzem, a samo pole zostaje nieoznaczone. Brzydko, ale użytkownik widzi komunikat.
   - W naiwnym kliencie, który po prostu woła `setError(klucz, …)` dla każdego wpisu,
     react-hook-form rejestruje błąd na polu, którego nie ma. Od tej chwili `handleSubmit`
     **odmawia wywołania `onSubmit`**, a jednocześnie **żaden input nic nie pokazuje**:
     formularz nie wysyła i nie mówi dlaczego.

   Kubełek na nieprzypisane komunikaty istnieje właśnie po to, żeby wymusić pierwszy wariant
   zamiast drugiego (test: `frontend/src/lib/apiErrorToForm.test.ts`). Sama konwersja:
   `backend/src/Http/ViolationFormatter.php`.
3. **Komunikaty 404 i 405 Symfony cytują URI i tablicę tras.** To wyciek szczegółów
   wewnętrznych, więc dla tych dwóch statusów podstawiam własny tekst (`:139`).

Świadomie **nie** używam RFC 7807 / `application/problem+json`. Powód: kontrakt
`{detail, code, fields}` przenosi się 1:1 z poprzedniej wersji aplikacji, a napisanie
subscribera od zera więcej uczy niż włączenie gotowego przełącznika. Problem Details to to,
co robi szerszy ekosystem — warto o tym wiedzieć i umieć uzasadnić wybór.

**Bearer to osobna ścieżka.** Odrzucony token *nie* przechodzi przez `failure_handler`
firewalla. `JWTAuthenticator::onAuthenticationFailure()` sam buduje
`JWTAuthenticationFailureResponse`, wysyła zdarzenie i zwraca to, co w zdarzeniu zostanie.
Dlatego podmiana odpowiedzi wymaga listenera na `Events::JWT_EXPIRED` / `JWT_INVALID` /
`JWT_NOT_FOUND` — `backend/src/EventSubscriber/JwtFailureSubscriber.php`. Ustawienie
`failure_handler` na firewallu `api` nie zrobiłoby **nic**, co przez chwilę wyglądało jak
błąd konfiguracji.

Rozróżnienie `token_expired` / `token_invalid` jest celowe: pierwsze znaczy „odśwież i
ponów", drugie „to poświadczenie już nie będzie dobre, wyloguj".

### 1.5 DTO, nie encja

`backend/src/Dto/Input/RegisterRequest.php`

Wejście użytkownika hydratuje **DTO**, nie encję. Encja wypełniona wprost z ciała żądania
jest o jedno zapomniane pole od pozwolenia dzwoniącemu ustawić cokolwiek, co mapowanie
wystawia.

Na wyjściu symetrycznie: `backend/src/Dto/Output/UserResource.php`. Serializacja encji
działałaby dziś i wyciekłaby jutro — pierwsza dodana kolumna pojawiłaby się w odpowiedzi
bez niczyjej decyzji. Przy okazji: `password` nie może wyciec, bo go tam po prostu nie ma.

**`#[UniqueEntity]` na DTO** wymaga opcji `entityClass:` (Symfony 6.2+). I jedno zdanie do
zapamiętania: to jest **uprzejmość, nie gwarancja** — dwie równoległe rejestracje mogą oba
przejść walidację, dlatego w bazie jest też unikalny indeks `uniq_user_email`. Potrzebne są
oba: constraint daje ładny komunikat przy polu, indeks daje faktyczną niezmienniczość.

**Konwerter nazw** ustawiony raz w `backend/config/packages/framework.yaml`:
`camel_case_to_snake_case`. Działa w obie strony — `password_confirm` z JSON-a ląduje na
`$passwordConfirm`, a `$createdAt` wychodzi jako `created_at`.

### 1.6 Klient API po stronie Reacta

`frontend/src/api/client.ts`

Jedna funkcja `apiFetch`, przez którą przechodzi wszystko. Na 401 (poza endpointami
autoryzacyjnymi) próbuje odświeżyć token i **jeden raz** ponawia żądanie.

Kluczowy fragment to `refreshAccessToken()` (`:131`) — *single flight*. Przy zimnym
załadowaniu strony leci naraz kilka zapytań, wszystkie bez tokenu, wszystkie dostają 401.
Bez tej ochrony każde z nich POST-owałoby na `/token/refresh`; przy włączonej rotacji
pierwsze unieważniłoby token, który niosą pozostałe, i użytkownik wylatywałby przy każdym
odświeżeniu strony.

Drugi szczegół, który kosztował mnie nieskończoną pętlę żądań — sekcja 1.9.

Efekt netto: po `F5` sesja wraca (`/auth/me` → 401 → `/token/refresh` → `/auth/me` → 200),
a token dostępu **nigdy** nie był nigdzie zapisany.

`frontend/src/lib/apiErrorToForm.ts` przenosi `fields` z odpowiedzi na pola formularza.
Klucze pasują, bo backend je skonwertował (sekcja 1.4) — to jest ta sama decyzja widziana
z drugiej strony.

### 1.7 Tailwind v4 i dark mode

`frontend/src/styles/index.css`

**W Tailwind v4 nie ma `tailwind.config.js` ani opcji `darkMode: 'class'`.** Prawie każdy
tutorial w sieci jest wciąż w kształcie v3 i po zastosowaniu produkuje CSS, który po prostu
nic nie robi — bez żadnego błędu. Dark mode to jedna linijka:

```css
@custom-variant dark (&:where(.dark, .dark *));
```

plus klasa `.dark` na `<html>`.

Trzy warstwy, w tej kolejności:

1. `@theme` — surowa paleta marki, niezależna od motywu (odcienie zieleni, promienie, cienie).
2. `@layer base` — tokeny **semantyczne** (`--surface`, `--foreground-muted`, `--border`),
   zdefiniowane dla obu motywów. Żaden kolor nie ma swojej jedynej definicji w bloku dark.
3. `@theme inline` — most, dzięki któremu istnieją klasy `bg-surface`, `text-foreground-muted`.

Preferencja ma **trzy** stany (jasny / systemowy / ciemny), a nie dwa — „idź za systemem" to
domyślna i najczęściej oczekiwana odpowiedź. `system` jest rozwiązywane w JS
(`frontend/src/app/theme.tsx`), a nie przez media query, żeby klasa na `<html>` była
jedynym źródłem prawdy o tym, co widać. Inline script w `index.html` ustawia klasę **przed
pierwszym malowaniem**, więc nie ma białego mignięcia.

Żółty i czerwony (`--booking`, `--sending-off`) są zarezerwowane dla kartek i mają
**inny odcień** niż `--danger` używany w błędach formularzy, żeby czerwona kartka nigdy nie
wyglądała jak stan błędu.

### 1.8 Testy

Trzy poziomy:

1. **Czysty unit, bez kernela** — `backend/tests/Http/ViolationFormatterTest.php`.
   Milisekundy, bez bazy. Tu docelowo trafi algorytm terminarza.
2. **Kontrakt HTTP** — `backend/tests/Api/AuthApiTest.php` na `WebTestCase`. Przypina
   statusy, dokładny kształt koperty i to, gdzie mieszkają tokeny.
3. **Frontend** — `frontend/src/api/client.test.ts` (w tym test, że równoległe odświeżenia
   zwijają się do jednego żądania) i `frontend/src/lib/apiErrorToForm.test.ts`.

**Baza testowa to osobny schemat MariaDB (`kickoff_test`), nie SQLite.** `pdo_sqlite` jest
dostępne i kusi, ale SQLite unieważniłby to, co ma być testowane: `SELECT … FOR UPDATE`
kompiluje się tam do niczego (więc testy blokad ze Stage 4 nic by nie dowodziły), semantyka
DDL i unikalnych indeksów się rozjeżdża, a obsługa `DATETIME` w MariaDB nie byłaby ćwiczona.

`dama/doctrine-test-bundle` opakowuje **każdy test w transakcję i wycofuje ją** — stąd
prędkość, której szukałoby się w SQLite. Foundry (`ResetDatabase`) buduje schemat
**uruchamiając prawdziwe migracje** (`orm.reset.mode: migrate` w
`backend/config/packages/zenstruck_foundry.yaml`), więc migracja, która przestała się
stosować, wywala testy zamiast wdrożenia.

Fabryki (`backend/tests/Factory/UserFactory.php`) zastępują `DoctrineFixturesBundle` —
przy kilkunastu encjach to dramatycznie mniej boilerplate'u.

### 1.9 Pułapki, na które realnie wpadłem

**Nieskończona pętla odświeżania.** Pierwsza wersja klienta na nieudanym refreshu wołała
`onSessionEnded`, a ten czyścił cache React Query. Wyczyszczony cache znaczy, że
zamontowane zapytanie startuje od nowa → 401 → refresh → porażka → czyszczenie → …
W sieci widać było kilkadziesiąt par żądań na sekundę.

Dwie zmiany naprawiły to razem (`frontend/src/api/client.ts:168`,
`frontend/src/main.tsx:15`):

- Zdarzenie „sesja wygasła" wysyłam **tylko wtedy, gdy sesja w ogóle istniała** (był token
  w pamięci). Przy zimnym starcie anonimowego użytkownika 401 i nieudany refresh to
  *normalny sposób rozpoznania, że nikt nie jest zalogowany* — nie awaria.
- Po wyczyszczeniu cache od razu **zasiewam** `qk.currentUser` wartością `null`. Pusty wpis
  zostałby natychmiast pobrany ponownie; zasiany jest świeży i nic nie startuje.

**Lexik wysyła zdarzenie pod nazwą, nie pod klasą.** `#[AsEventListener(event:
AuthenticationSuccessEvent::class)]` nie odpalił się ani razu — po cichu, bez błędu. Poprawnie:
`#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]`
(`backend/src/EventSubscriber/AuthenticationSuccessSubscriber.php:26`).

**Podwójne mapowanie `RefreshToken`.** Bundle dostarcza swoją encję jako *mapped superclass*
z mapowaniem XML. Zadeklarowanie tych samych kolumn atrybutami w klasie potomnej daje
`Duplicate definition of column 'id'`. Poprawnie: dziedziczyć i **nie mapować niczego
ponownie** (`backend/src/Entity/RefreshToken.php`). Ładna demonstracja, że mapped superclass
to naprawdę dziedziczone mapowanie.

**`lexik:jwt:generate-keypair` pod XAMPP-em.** Kończy się `error:80000003:system library::No
such process`, bo PHP-owy OpenSSL nie znajduje pliku konfiguracyjnego. Lekarstwo:
`OPENSSL_CONF=C:/xampp/php/extras/ssl/openssl.cnf` przed komendą. Opisane w README.

**MariaDB przebrana za MySQL.** `serverVersion` w `DATABASE_URL` **musi** mieć sufiks
`-MariaDB`, inaczej DBAL wybiera platformę MySQL-ową. Objaw: migracje odrzucane przez
serwer albo `migrations:diff`, który nigdy nie wraca pusty. Do tego MariaDB 10.4 wciąż ma
domyślnie latin1 — stąd `default_table_options` w `backend/config/packages/doctrine.yaml`.

**Rotacja refresh tokena kontra React StrictMode.** Przy `single_use: true` podwójne
wywołanie efektów w trybie deweloperskim potrafi spalić token i wylogować przy każdym
odświeżeniu. Dlatego: refresh **wyłącznie** z wnętrza `apiFetch` (nigdy z `useEffect`),
single-flight promise, i `JWT_REFRESH_SINGLE_USE=0` w dev / `1` w produkcji.

**`erasableSyntaxOnly` w tsconfig.** Vite ustawia to domyślnie. Zabrania konstrukcji
TypeScriptu, które nie znikają przez samo usunięcie — czyli m.in. **promowanych właściwości
konstruktora**. Stąd `ApiError` ma pola wypisane ręcznie (`frontend/src/api/client.ts:27`).

**Fast refresh i mieszane eksporty.** Moduł eksportujący komponent *i* hooka wypada z fast
refreshu i cicho przechodzi na pełne przeładowanie strony. Stąd podział na
`frontend/src/app/theme.tsx` (sam provider) i `frontend/src/app/theme-context.ts`
(kontekst + `useTheme`).

**Typowane stałe klasowe** (`public const string FOO`) to PHP 8.3. Na 8.2 to `ParseError`.

### Pytania na rozmowę — Stage 1

**Dlaczego stateless firewall nie potrzebuje ochrony CSRF?**
Bo CSRF opiera się na tym, że przeglądarka *automatycznie* dołącza poświadczenie do żądania
z obcej strony. Poświadczeniem jest tu nagłówek `Authorization`, który obca strona musiałaby
sama ustawić — a tego nie może zrobić bez dostępu do tokenu. Uwaga na niuans: w tej
aplikacji istnieje ciasteczko (refresh), więc endpoint `/token/refresh` **ma** powierzchnię
CSRF i broni się `SameSite=Lax`, POST-em i wąską ścieżką.

**Po co dwa tokeny, skoro jeden by wystarczył?**
Bo mają sprzeczne wymagania. Access token musi być sprawdzalny bez zaglądania do bazy
(stąd podpis) i przez to jest nieodwoływalny — więc musi być krótki. Sesja użytkownika ma
trwać tygodniami — więc potrzebny jest długi token, który *da się* unieważnić, czyli trzymany
w bazie. Rozdzielenie pozwala mieć jedno i drugie.

**Dlaczego access token nie może iść do `localStorage`?**
Bo `localStorage` czyta każdy skrypt na stronie, łącznie z wstrzykniętym przez XSS, a to co
stamtąd wyciekło, działa dalej po zamknięciu karty. Zmienna modułowa ginie z kartą.
Kontrargument, który warto znać: to nie chroni przed XSS-em jako takim — atakujący wciąż
może działać w kontekście strony — ale odbiera mu *trwałe* poświadczenie do wyniesienia.

**Co się stanie, gdy dwa żądania jednocześnie dostaną 401?**
Bez single-flight: dwa równoległe refreshe. Przy rotacji drugi używa już unieważnionego
tokenu, dostaje 401, aplikacja wylogowuje użytkownika. Dlatego `refreshAccessToken()`
zwraca jedną współdzieloną obietnicę.

---

## Stage 2 — organizacje, role per-org, scope resolver

### 2.1 Rola nie jest rolą Symfony

`backend/src/Entity/OrganizationRole.php`

Odruch mówi: „admin? wrzuć `ROLE_ADMIN` do `User::getRoles()`". To by znaczyło **administrator
wszystkiego** — a w tej aplikacji ta sama osoba może być właścicielem jednej ligi i biernym
obserwatorem w drugiej. Dlatego rola siedzi na wierszu `organization_memberships`, a
`User::getRoles()` zwraca dla każdego to samo `['ROLE_USER']`.

Enum jest **backed** (`: string`) i zmapowany przez `enumType:`, więc w bazie widać `OWNER`,
a nie liczbę, której nikt nie zinterpretuje z klienta SQL.

`OrganizationRole::assignable()` zwraca `[Admin, Member]` — i to jest **jedyna** lista
dozwolonych ról w API. `AddMemberRequest` i `UpdateMemberRoleRequest` używają jej przez
`#[Assert\Choice(callback: ...)]`, więc nie ma drugiego miejsca, które trzeba trzymać w
zgodzie. Konsekwencja: `"role": "OWNER"` w ciele żądania to błąd walidacji, a nie sposób na
zrobienie drugiego właściciela.

### 2.2 Scope i ValueResolver

To jest sedno całego etapu.

Trasy są głęboko zagnieżdżone (`/organizations/{o}/leagues/{l}/seasons/{s}/…` od Stage 3).
Naiwne podejście to w każdym kontrolerze: pobierz organizację, sprawdź membership, pobierz
ligę, sprawdź czy należy do organizacji… — i wystarczy raz o czymś zapomnieć.

Zamiast tego jest `ScopeInterface` (`backend/src/Scope/ScopeInterface.php`) i fabryka, która
buduje go **jednym zapytaniem joinującym membership**
(`OrganizationMembershipRepository::findForUserAndOrganization`). Brak wiersza →
`NotFoundHttpException`.

`backend/src/Http/ValueResolver/ScopeValueResolver.php` wstrzykuje to do kontrolera po typie:

```php
#[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
public function update(OrganizationScope $scope, #[MapRequestPayload] OrganizationRequest $payload): JsonResponse
```

To jest następca `ParamConvertera` z SensioFrameworkExtraBundle — i lepszy, bo zamiast
„pobierz encję o tym id" odpowiada na pytanie „rozwiąż całą ścieżkę w obiekt, do którego
wołający ma prawo". **Kontroler, który trzyma scope, trzyma dowód uprawnienia**: nie ma
zapytania do zapomnienia, bo nie da się zdobyć tego obiektu inaczej.

Jedno rozwiązanie załatwia cztery rzeczy naraz:

1. zagnieżdżenie tras — `{organizationId}` nie pojawia się w żadnej sygnaturze kontrolera,
2. regułę 404 zamiast 403,
3. spójność łańcucha (od Stage 3: nie wejdziesz w sezon 5 przez ligę 9),
4. N+1 na rodzicach — organizacja przyjeżdża `addSelect`em razem z membershipem.

Rejestracja: żadna. `ValueResolverInterface` jest autokonfigurowane, wystarczy że klasa leży
w `src/`.

### 2.3 404 zamiast 403

Reguła: **obcy dostaje 404, nigdy 403.**

403 znaczy „to istnieje, ale nie dla ciebie" — czyli potwierdza istnienie zasobu. Kto zgadnie
`/organizations/1`, `/2`, `/3` i będzie rozróżniał 403 od 404, ten zmapuje rozmiar systemu i
ważność identyfikatorów, nie mając w nim żadnego konta.

W `ScopeFactory` nie ma gałęzi „organizacja istnieje, ale nie jesteś członkiem" — nie da się
takiej napisać, bo zapytanie już zawiera join membershipu. Brak wiersza znaczy jedno i to
samo.

Pilnuje tego test z `#[DataProvider]` na **wszystkich trzech czasownikach**
(`backend/tests/Api/OrganizationApiTest.php`), bo najłatwiej przeoczyć to na PATCH albo
DELETE, gdzie odruchowo pisze się „a, tu przecież i tak trzeba 403".

Frontend musi mówić to samo. `OrganizationPage` na 404 pokazuje „This organization is not
available to you" — zdanie **prawdziwe zarówno gdy zasób nie istnieje, jak i gdy istnieje
cudzy**. „Nie masz dostępu do tej organizacji" oddałoby dokładnie tę informację, którą kod
statusu zataił.

### 2.4 Voter, który nie odpytuje bazy

`backend/src/Security/Voter/OrganizationVoter.php`

Zdanie do zapamiętania: **scope decyduje, czy zasób dla ciebie istnieje; voter decyduje, co ci
wolno z zasobem, który istnieje.**

- Samo scopowanie w repozytorium nie wyrazi „member może czytać, ale nie pisać".
- Sam voter nie wyrazi „ten zasób jest dla ciebie niewidoczny" bez wyciekania 403.

Potrzebne są oba i odpowiadają na różne pytania.

Voter głosuje na `ScopeInterface`, w którym rola **już jest wczytana**, więc autoryzacja
kosztuje zero dodatkowych zapytań. To praktyczna różnica wobec typowego votera, który dostaje
encję i dopiero szuka uprawnień.

`ORG_VIEW` zwraca zawsze `true` — i to nie jest dziura. Dotarcie do tej linii oznacza, że
zapytanie membershipowe zwróciło wiersz; dowód już się odbył.

### 2.5 Niezmienniki właściciela

Trzy, wszystkie wymuszone po stronie serwera:

1. **Organizacja i własność powstają razem albo wcale** — `OrganizationManager::create()`
   w `wrapInTransaction`. Organizacja bez właściciela to wiersz, nad którym nikt na świecie
   nie ma władzy: nie da się nim zarządzać ani go usunąć, zostaje tylko migracja.
2. **Członkostwa właściciela nie da się zmienić ani usunąć** przez API →
   `OwnerMembershipIsProtectedException`, 403, kod `owner_membership_protected`. 403, nie 409,
   bo to nie kwestia bieżącego stanu danych — żadna sekwencja żądań tego nie odblokuje.
   Strażnik jest w **jednym** miejscu (`guardOwner`), bo dwa oddzielne sprawdzenia w dwóch
   kontrolerach to sposób na to, żeby jedno kiedyś wypadło.
3. **API nie wyprodukuje drugiego właściciela** — patrz `assignable()` w 2.1.

Osobno: `findOneInOrganization()` bierze organizację **do zapytania**, a nie porównuje jej po
pobraniu. Id członkostwa jest unikalne globalnie, więc gdyby porównanie zostało kiedyś
pominięte, admin jednej organizacji edytowałby skład innej, zgadując identyfikatory.

### 2.6 Slug: sufiks zamiast odmowy

`backend/src/Service/SlugGenerator.php`

Dwie organizacje mogą się legalnie nazywać tak samo. Skoro slug jest **wyprowadzany z nazwy**,
odmowa zapisu oznaczałaby błąd przy polu, którego użytkownik w ogóle nie wypełniał. Dlatego
`podkarpacka-liga-amatorska`, a przy kolizji `-2`, `-3`.

Detal, który gryzie dopiero przy danych: `new AsciiSlugger()` **bez locale** używa ogólnej
tablicy transliteracji i polskie nazwy wychodzą pokaleczone. `new AsciiSlugger('pl')` daje
`Łódzki Związek Piłki` → `lodzki-zwiazek-pilki`. Jest na to test.

Slug ma `VARCHAR(64)`, nie 255. Pod utf8mb4 InnoDB ma limit 3072 bajtów na indeks, a od
Stage 3 slugi wchodzą w złożone unikalne indeksy razem z kluczem obcym.

### 2.7 Pułapki Stage 2

**`$code` w wyjątku.** `class ConflictException` z `private readonly string $code` to **fatal
error kompilacji**: `\Exception` ma już własne `$code`, a przedeklarowanie niereadonly
property jako readonly jest zabronione. Stąd `$errorCode`.

**Enum przy hydratacji skalarnej.** `->select('m.role AS role')` **nie** zwraca stringa —
Doctrine stosuje `enumType` kolumny także do selecta skalarnego, więc przyjeżdża instancja
`OrganizationRole`. `(string) $row['role']` daje „Object of class … could not be converted to
string" i 500.

**Typowana właściwość enumowa w DTO psuje komunikat.** `public OrganizationRole $role`
wygląda porządnie, ale nieznana wartość jest odrzucana przez **serializer, zanim walidacja w
ogóle ruszy** — i użytkownik dostaje `This value should be of type int|string.` Prawda, i
zero pożytku dla kogoś wypełniającego formularz. Dlatego DTO trzyma `string`, a walidację
robi `#[Assert\Choice]` z `assignableValues()`; enum wychodzi z metody `role()`, wywołanej
już po walidacji. Efekt: `SUPERVISOR` (nie ma takiego case'a), `OWNER` (jest, ale nie jest
przydzielalny) i `admin` (zła wielkość liter) dają ten sam komunikat *Choose a valid role.*

**`wrapInTransaction` daje mniej, niż się wydaje.** Pojedynczy `flush()` **już jest
atomowy** — Doctrine opakowuje każdy commit własną transakcją — więc oba INSERT-y i tak
przeszłyby albo nie przeszły razem. Transakcja kupuje tu wspólną granicę dla *odczytu sluga
i zapisu*. Czego **nie** kupuje: odporności na wyścig, bo bez `SELECT … FOR UPDATE` obie
transakcje mogą odczytać sluga jako wolny. Drugą zatrzymuje dopiero unikalny indeks —
naruszeniem integralności, czyli dziś pięćsetką. Wzorzec z blokadą przychodzi przy
generowaniu terminarza, gdzie wyścig jest regułą, a nie teorią.

**`cascade: ['persist']` na kolekcji.** Bez tego membership utworzony w konstruktorze albo w
fabryce testowej nie trafia do bazy, bo nikt go nie persystuje jawnie.

**`single_line_throw` z presetu `@Symfony`** zwija każdy `throw` do jednej linii — konstruowany
wyjątek z pięcioma argumentami staje się linią na 200 znaków. Wyłączone w
`.php-cs-fixer.dist.php`.

**Tailwind preflight kontra natywny `<dialog>`.** Modalny `<dialog>` jest wyśrodkowany przez
własny styl przeglądarki: `inset: 0; margin: auto`. Preflight zeruje **wszystkie** marginesy,
więc dialog po cichu przykleja się do lewego górnego rogu. Lekarstwo to jedna klasa `m-auto`
(`frontend/src/components/ui/dialog.tsx`). Poza tym `showModal()` daje za darmo pułapkę
fokusa, `inert` na tle, warstwę top-layer i obsługę Escape — czyli wszystko to, co ręcznie
robione modale robią źle.

**`setState` w efekcie.** Pierwsza wersja dialogu czyściła formularz efektem reagującym na
`open`. Lint słusznie zaprotestował: to dodatkowy render dla czegoś, o czym zdarzenie
zamknięcia i tak wie. Teraz czyszczenie dzieje się w `close()`.

### Pytania na rozmowę — Stage 2

**Dlaczego 403 dla obcej organizacji to błąd, a nie uprzejmość?**
Bo 403 potwierdza istnienie zasobu. Iterując po identyfikatorach i rozróżniając 403 od 404
można zmapować system, nie mając w nim konta. 404 dla wszystkiego, czego nie widzisz, nie
zdradza nic.

**Czym różni się Voter od `access_control`?**
`access_control` działa na ścieżce URL i nie wie nic o obiekcie. Voter dostaje **podmiot** i
odpowiada na pytanie zależne od danych („czy ta osoba może edytować *tę* organizację").
Tutaj `access_control` mówi tylko „pod `/api` trzeba być zalogowanym", a całą resztę
rozstrzygają scope i voter.

**Dlaczego rola nie jest w tokenie JWT?**
Bo jest per-organizacja, a token jest jeden na sesję. Wrzucenie tam ról znaczyłoby ponowne
wydawanie tokenu przy każdej zmianie uprawnień — a do tego czasu odwołany admin dalej byłby
adminem, bo access tokenu nie da się unieważnić.

**Po co `wrapInTransaction` przy tworzeniu organizacji?**
Bo organizacja i członkostwo właściciela muszą powstać razem albo wcale. Sam INSERT
organizacji, po którym coś pęknie, zostawia wiersz, nad którym nikt nie ma władzy: nie da się
go edytować ani usunąć przez API.

---

## Stage 3a — ligi, kluby, zawodnicy i maszyneria list

### 3a.1 Klub nie należy do ligi

Najbardziej kuszący błąd modelowania w tej domenie: `Team` z kluczem obcym do `League`.

Klub, który awansuje, spada albo przenosi się między rozgrywkami, **jest tym samym klubem**.
Trzymanie go pod ligą rozwidlałoby jego tożsamość przy każdym ruchu, a razem z nią historię:
tabele, strzelców, spotkania. Dlatego `Team` i `Player` należą do **organizacji**, a przypisanie
„ten klub gra w tym sezonie tej ligi" to osobny wiersz (`SeasonTeam`, Stage 3b).

Ta sama logika przy zawodniku. `Player` to osoba; to, że w sezonie 2026 gra w Stali z numerem 9
i jest kapitanem, to `RosterEntry`. Bez tego rozdziału transfer oznaczałby wpisanie zawodnika
od nowa, a jego dorobek zostałby przy poprzednim klubie.

`Player::$dateOfBirth` jest **nullowalne** i to nie jest lenistwo. W lidze amatorskiej
zawodnik bywa zgłaszany, zanim ktokolwiek sprawdzi datę urodzenia. Schemat, który tego
zabrania, nie sprawia, że dane są lepsze — sprawia, że ktoś wpisuje `1900-01-01`. A to jest
gorsze, bo *wygląda* jak dane.

### 3a.2 ListQuery i MapQueryString

`backend/src/Dto/Input/ListQuery.php`

Cztery parametry, których używa każda kolekcja: `search`, `order`, `page`, `page_size`.
Konsumowane przez `#[MapQueryString]`, czyli **tym samym serializerem i walidatorem** co ciało
żądania. Konsekwencja: `page_size=0` to 422 z komunikatem, a nie dzielenie przez zero trzy
warstwy niżej. Konwerter nazw działa tak samo, więc `page_size` z query stringa ląduje na
`$pageSize`.

Domyślna wartość parametru w sygnaturze (`ListQuery $query = new ListQuery()`) jest konieczna —
bez niej żądanie bez query stringa w ogóle by nie zmapowało.

### 3a.3 Paginacja opt-in

Bez `page` i `page_size` endpoint zwraca **zwykłą tablicę JSON**. Z którymkolwiek z nich —
kopertę `{count, page, page_size, next, previous, results}`.

Uzasadnienie: w tej aplikacji dominują kolekcje po kilkanaście wierszy (12 klubów, 18
zawodników w składzie). Owijanie ich w kopertę to ceremonia bez treści, a klient i tak musiałby
sięgać po `.results`. Kto potrzebuje przejść po długiej liście, prosi o stronę.

`next` i `previous` to **numery stron**, nie adresy URL. DRF zwraca adresy i dla API do
crawlowania to sensowny wybór, ale wtedy w każdej odpowiedzi siedzi publiczna nazwa hosta —
która musi być poprawna za proxy, w testach i w kontenerze. Klient, który zna endpoint, potrafi
dopisać `?page=`.

`Doctrine\ORM\Tools\Pagination\Paginator` istnieje po to, żeby `COUNT` po joinie był
poprawny. Argument `fetchJoinCollection` jest tu `false`, bo te zapytania joinują wyłącznie
relacje to-one. Trzeba go włączyć, gdy zapytanie fetch-joinuje **kolekcję** — inaczej `LIMIT`
obcina joinowane wiersze zamiast encji i strona wraca krótsza, niż powinna.

### 3a.4 Sortowanie: whitelista i remis

`backend/src/Repository/Listing.php`

Whitelista mapuje **nazwę z API** na **wyrażenie DQL**:

```php
private const ORDERING = [
    'name' => 'l.name',
    'slug' => 'l.slug',
    'created_at' => 'l.createdAt',
];
```

Dzięki temu wołający nigdy nie nazywa kolumny. Zmiana nazwy właściwości nie psuje API, a
`order` nie posłuży do sortowania po czymś, czego zasób w ogóle nie wystawia.

Nieznane pole to **400 z listą dozwolonych**, nie ciche zignorowanie. Sortowanie, które nic nie
robi, to błąd, który dożywa produkcji, bo odpowiedź nadal wygląda sensownie — tylko w złej
kolejności.

Drugi szczegół, mniej oczywisty: po polu sortowania zawsze dokładany jest **remis po kluczu
głównym**. Bez niego dwa kluby o tej samej nazwie mogą zamienić się miejscami między
żądaniami, a czytelnik przeglądający strony zobaczy jeden z nich dwa razy, a drugiego nigdy.
Przy stronicowaniu to nie teoria — to zależy od planu zapytania.

### 3a.5 Slug per organizacja

Unikalność sluga jest **na parę (organizacja, slug)**, nie globalna. Dwa związki mogą
prowadzić „Ligę Okręgową" i żaden nie ma praw do tych słów. Test to przypina.

Sufiks `-2` pojawia się dopiero przy kolizji **wewnątrz jednej** organizacji.

### 3a.6 URL jako stan listy

`frontend/src/hooks/useListParams.ts`

Wyszukiwanie i numer strony żyją w query stringu, nie w `useState`. Kosztuje to tyle samo, a
daje: możliwość podlinkowania przefiltrowanego widoku, działający przycisk wstecz i to, że
odświeżenie nie wyrzuca cichaczem na pierwszą stronę bez filtra.

Zmiana wyszukiwania **kasuje numer strony**, bo strona 4 węższego wyniku zwykle nie istnieje —
a pusta tabela czyta się jak „nie ma takiego klubu", nie jak „zła strona".

Zakładki też są trasami (`/organizations/1/clubs`), nie stanem komponentu. `aria-current`
przychodzi wtedy od routera, a nie z ręcznie pilnowanej flagi.

`placeholderData: (previous) => previous` w TanStack Query sprawia, że przy przejściu na kolejną
stronę tabela przez moment pokazuje poprzednie wiersze zamiast się opróżniać. Drobiazg, który
robi całą różnicę w odczuciu płynności.

### 3a.7 Pułapki Stage 3a

**Git Bash psuje nie-ASCII w `curl -d`.** Wysyłanie inline `-d '{"name":"Stal Rzeszów"}'`
kończyło się `400 invalid_payload`, co wyglądało jak błąd aplikacji. To samo ciało z pliku
(`--data-binary @plik.json`) daje 201 i slug `stal-rzeszow`. Morał ogólniejszy: **zanim zgłosisz
błąd w kodzie, sprawdź, czy nie zgłaszasz błędu swojego narzędzia.**

**`array_values()` na czymś, co już jest listą** — PHPStan słusznie krzyczy, że wywołanie nic
nie robi. Wynik `array_map` po liście jest listą.

**Pole w `knownFields`, którego nie ma w formularzu.** Dialogi ligi i klubu nie mają inputu na
slug, więc `'slug'` na liście znanych pól to błąd typów (i logiki): komunikat o slugu należy do
banera formularza, bo nie ma pola, przy którym mógłby stanąć.

**`setState` w efekcie, znowu.** `SearchInput` synchronizował pole z URL-em efektem. Poprawny
wzorzec z dokumentacji Reacta to **korekta stanu w trakcie renderu**:

```tsx
if (value !== lastValue) {
  setLastValue(value)
  setDraft(value)
}
```

Efekt najpierw namalowałby nieaktualną wartość, a dopiero potem przerenderował.

**Fast refresh, znowu.** `OrganizationPage.tsx` eksportował komponent *i* hooka kontekstu →
moduł wypada z fast refreshu. Hook wyprowadzony do `organization-context.ts`.

### Pytania na rozmowę — Stage 3a

**Czemu `Team` nie ma klucza obcego do `League`?**
Bo klub przetrwa awans, spadek i zmianę rozgrywek, a klucz obcy rozwidliłby jego tożsamość
przy każdym takim ruchu — razem z całą historią. Związek „klub gra w tym sezonie" to osobny
wiersz, bo to fakt o sezonie, nie o klubie.

**Po co `Doctrine\ORM\Tools\Pagination\Paginator`, skoro jest `setMaxResults`?**
Bo przy joinie jedna encja daje wiele wierszy wyniku. `LIMIT 10` obcina wtedy wiersze, nie
encje, więc strona wraca krótsza; a naiwny `COUNT(*)` liczy wiersze po joinie, nie encje.
Paginator rozwiązuje to zapytaniem po identyfikatorach. Flaga `fetchJoinCollection` mówi mu,
czy to w ogóle konieczne.

**Dlaczego whitelista mapuje nazwę na wyrażenie DQL, a nie na nazwę kolumny?**
Bo nazwa z API to kontrakt, a nazwa właściwości to szczegół implementacyjny. Mapowanie
rozdziela je: można przemianować właściwość bez zmiany API, a `order` nie posłuży do
sortowania po czymś, czego zasób nie wystawia.

**Co się psuje przy stronicowaniu bez remisu w `ORDER BY`?**
Kolejność wierszy o równych wartościach nie jest zdefiniowana i może się różnić między
zapytaniami. Przy `LIMIT/OFFSET` czytelnik zobaczy wtedy jeden wiersz dwa razy, a innego nigdy
— i nic tego nie zgłosi, bo każda odpowiedź z osobna jest poprawna.

---

## Stage 3b — sezony, rejestracja klubów i składy

### 3b.1 Własny Constraint: kiedy naprawdę jest potrzebny

`backend/src/Validator/SeasonName.php` + `SeasonNameValidator.php`

Sezon nazywa się „2026" albo „2026/27". Regex sprawdzi **kształt** `\d{4}(/\d{2})?` — ale nie
sprawdzi **arytmetyki**: „2026/27" to sezon, a „2026/29" to literówka i żaden wzorzec ich nie
rozróżni. Trzeba sparsować obie połówki i je porównać, a to już jest praca dla walidatora.

Anatomia jest prosta i warto ją znać na pamięć:

- **Constraint** to obiekt-wartość z komunikatami i opcjami. Sam nic nie robi.
- **ConstraintValidator** to **usługa** — może mieć wstrzyknięte zależności.
- Symfony łączy je po nazwie: `SeasonName` → `SeasonNameValidator`. Bez konfiguracji.
- Walidator **nie sprawdza pustości**. `null` i `''` przepuszcza, bo od tego jest `NotBlank`.
  Walidator, który sam wymusza „wymagane", nie da się użyć na polu opcjonalnym — i tak działa
  każdy constraint w Symfony.

Test (`backend/tests/Validator/SeasonNameValidatorTest.php`) używa
`ConstraintValidatorTestCase`, który podstawia sztuczny kontekst wykonania — całość idzie bez
kernela i bez bazy, mikrosekundy na przypadek.

I ten test **od razu znalazł błąd**: `2099/00` to sezon 2099–2100, a moja arytmetyka
(„weź stulecie pierwszego roku") liczyła 2000. Poprawka to trzy linijki, ale sam błąd
odezwałby się raz na sto lat — czyli nigdy w testach ręcznych.

Uwaga na kontrast: `end_date >= start_date` **nie** dostało własnego constraintu, bo
`#[Assert\GreaterThanOrEqual(propertyPath: 'startDate')]` robi dokładnie to samo. Pisanie
własnego tam, gdzie wbudowany pasuje, to nie nauka, tylko kod do utrzymania.

### 3b.2 Gdzie mieszka która reguła

Podział, który warto umieć uzasadnić:

| Reguła | Gdzie | Dlaczego |
| --- | --- | --- |
| „2026/29 to nie jest nazwa sezonu" | Constraint | dotyczy **samej wartości** |
| „koniec nie przed początkiem" | wbudowany Constraint | dotyczy dwóch pól tego samego obiektu |
| „klub musi być z tej organizacji" | `SquadManager` | potrzebuje **kontekstu** żądania |
| „numer 9 jest zajęty w tym składzie" | `SquadManager` | potrzebuje **bazy i składu** |

Walidator nie ma jak się dowiedzieć, **która organizacja pyta** — nie widzi trasy ani tokenu.
Można mu to wstrzyknąć przez `RequestStack`, ale wtedy powstaje walidator, który działa tylko
w kontekście HTTP i nie da się go przetestować bez żądania. Reguły zależne od kontekstu żyją
więc w warstwie domenowej, dokładnie tam gdzie `addMember` ze Stage 2.

### 3b.3 NULL-e w unikalnym indeksie

Numer na koszulce jest unikalny **w składzie**, ale kolumna jest nullowalna, bo skład bywa
wpisywany zanim numery zostaną rozdane.

Naiwna obawa: „unikalny indeks nie pozwoli na dwóch zawodników bez numeru". Nieprawda — **SQL
traktuje NULL-e jako różne**, więc zwykły `UNIQUE (season_team_id, shirt_number)` dopuszcza
dowolnie wielu nienumerowanych i jednocześnie odrzuca dwie dziewiątki. Żadnego indeksu
częściowego nie trzeba, co jest o tyle wygodne, że **MariaDB ich nie ma**.

Aplikacja, którą to zastępuje, dokładała tam warunek `WHERE shirt_number IS NOT NULL`. Na
PostgreSQL to działa i jest nadmiarowe; tutaj byłoby po prostu niemożliwe.

Test na to jest, bo to dokładnie ta reguła, którą ktoś „poprawi" przy następnej migracji.

### 3b.4 Kapitan: degradacja zamiast odmowy

Kapitan jest najwyżej jeden na skład — i tego akurat **nie da się oddać schematowi** na
MariaDB, bo wymagałoby to indeksu częściowego (`UNIQUE WHERE captain`). Istnieje sztuczka z
kolumną generowaną (`IF(captain, season_team_id, NULL)` + unikalny indeks), ale kolumna
generowana rozjeżdża `doctrine:schema:validate` i sprawia, że `migrations:diff` przestaje
wracać pusty — cena wyższa niż zysk.

Więc reguła żyje w `SquadManager`. Ale **nie jako odmowa**: nadanie opaski kapitana
**degraduje poprzedniego**. Odmowa zmusiłaby operatora do szukania, kto ją aktualnie ma —
księgowość, którą komputer wykona lepiej niż człowiek. Cała operacja idzie w jednej
transakcji, więc nie ma momentu z dwoma kapitanami.

Uczciwie: to jest jedyne wymuszenie tego niezmiennika. Dwa równoległe żądania mogą teoretycznie
zostawić dwóch kapitanów. Przy jednym operatorze wpisującym skład to nie jest realne ryzyko —
ale **jest** zapisane, a nie przemilczane.

### 3b.5 Cztery poziomy zagnieżdżenia

`/organizations/{o}/leagues/{l}/seasons/{s}/teams/{st}/roster/{r}`

`SeasonTeamScope` niesie cały łańcuch: organizacja → liga → sezon → zarejestrowany klub, i
**wszystko to jedno zapytanie** (`SeasonTeamRepository::findScoped`). Kontroler dostaje gotowy
obiekt i nie sprawdza niczego.

Zagnieżdżenie w URL-u znaczy coś tylko wtedy, gdy łańcuch jest **weryfikowany**. Sezon 1
osiągnięty przez ligę 2, do której nie należy, musi być brakującym wierszem — jest na to test i
sprawdziłem to też ręcznie: `404 not_found`.

### 3b.6 Dwa N+1 w jednym endpointcie

Lista klubów sezonu zwraca też `squad_size`. Pierwsza wersja miała **dwa** N+1 naraz i tylko
jeden był oczywisty:

1. Brak `addSelect('t')` → klub jest leniwym proxy, budzonym raz na wiersz.
2. `$seasonTeam->getRosterEntries()->count()` na niezainicjalizowanej kolekcji **ładuje całą
   kolekcję**. Czyli jedno zapytanie na klub, pobierające wiersze, których nikt nie chciał,
   tylko po to, żeby je policzyć i wyrzucić.

Drugi jest paskudniejszy, bo wygląda niewinnie: `->count()` czyta się jak `COUNT(*)`.

Test nie sprawdza progu („mniej niż 8 zapytań"), tylko **stałość**: to samo żądanie dla 3 i dla
10 klubów musi kosztować tyle samo zapytań. Mocniejsze twierdzenie i nie ma magicznej liczby do
utrzymywania.

Rozwiązanie to dwa zapytania niezależne od liczby klubów: encje z joinem klubu, a liczności
osobnym `GROUP BY`. Celowo **nie** jednym zapytaniem z `COUNT()` obok hydratowanych encji — to
wymaga `GROUP BY` po wszystkich wybranych kolumnach i właśnie tam zaczynają się różnice
`ONLY_FULL_GROUP_BY` między MySQL a MariaDB.

### 3b.7 Data to nie moment

Zobaczyłem to na ekranie: `2026-08-15T00:00:00+02:00 – ongoing`.

Serializer domyślnie emituje RFC 3339, więc `date_immutable` dostaje **wymyśloną północ i
wymyśloną strefę**. To nie jest tylko brzydkie: klient godzinę na zachód wyrenderuje dzień
wcześniejszy.

Lekarstwo to atrybut na właściwości DTO:

```php
#[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
public \DateTimeImmutable $startDate,
```

`created_at` **zostaje** pełnym RFC 3339, bo utworzenie wiersza naprawdę jest momentem. Oba
przypięte testami.

### 3b.8 Pułapki Stage 3b

**`Doctrine\DBAL\Logging\DebugStack` już nie istnieje** — usunięty w DBAL 4. Liczenie zapytań
robi się dziś przez profiler: `$client->enableProfiler()`, potem
`$profile->getCollector('db')->getQueryCount()`. W środowisku testowym profiler ma
`collect: false`, więc nic nie zbiera dopóki test o to nie poprosi — reszta suite'u nic nie
płaci.

**NULL-e nie sortują się na końcu same z siebie.** W MariaDB `ORDER BY shirt_number ASC` stawia
NULL-e **pierwsze**. Skład ma je mieć na końcu, więc repozytorium sortuje po wyrażeniu
`CASE WHEN r.shirtNumber IS NULL THEN 1 ELSE 0 END`, a dopiero potem po numerze.

**Numer koszulki zapisywany na `blur`, nie na `change`.** Wpisując „12" przechodzi się przez
„1" — a przy zapisie na każdą zmianę poleciałoby żądanie o numer, który ktoś inny może już
nosić, i pole zapaliłoby się na czerwono w połowie pisania.

### Pytania na rozmowę — Stage 3b

**Kiedy pisać własny Constraint, a kiedy nie?**
Gdy reguła dotyczy samej wartości i żaden wbudowany jej nie wyraża — jak arytmetyka lat w
nazwie sezonu. Gdy reguła potrzebuje kontekstu żądania (kto pyta, o którą organizację), jej
miejsce jest w warstwie domenowej: walidator nie ma jak się tego dowiedzieć, a wstrzyknięcie mu
`RequestStack` daje walidator, którego nie da się przetestować bez HTTP.

**Czy unikalny indeks na nullowalnej kolumnie zablokuje wiele NULL-i?**
Nie. SQL traktuje NULL-e jako różne, więc `UNIQUE (season_team_id, shirt_number)` dopuszcza
dowolnie wielu zawodników bez numeru i odrzuca dwóch z tym samym. Dlatego indeks częściowy jest
tu niepotrzebny — co dobrze się składa, bo MariaDB go nie ma.

**Dlaczego `->count()` na kolekcji Doctrine bywa pułapką?**
Bo na niezainicjalizowanej kolekcji ładuje **całą kolekcję**, a nie wykonuje `COUNT`. W pętli
po dwunastu klubach to dwanaście zapytań pobierających dane, których nikt nie użyje.
`fetch: 'EXTRA_LAZY'` zamienia to na `COUNT` — wciąż jeden na klub. Stałym kosztem jest dopiero
osobne zapytanie z `GROUP BY`.

**Co jest złego w `"2026-08-15T00:00:00+02:00"` jako dacie startu sezonu?**
Wymyśla północ i strefę czasową, których wartość nie ma. Klient w innej strefie może przesunąć
to na dzień wcześniejszy, a porównania dat zaczynają zależeć od tego, kto pyta.

---

## Stage 4 — generowanie terminarza

### 4.1 Algorytm bez frameworka

`backend/src/Domain/Fixture/RoundRobinScheduler.php`

Klasa nie zna Doctrine, encji ani kontenera. Na wejściu `list<int>` identyfikatorów, na wyjściu
`list<FixturePairing>` — zwykłe liczby.

To nie jest czystość dla czystości. Błąd w terminarzu objawia się jako „czternasta kolejka
wygląda dziwnie" trzy tygodnie później, a jedyny sposób na pewność to przetestować algorytm
**wyczerpująco**: każda para spotyka się raz, nikt nie gra dwa razy w kolejce, pauzy rozkładają
się równo, gospodarze się równoważą. Taki zestaw testów musi być natychmiastowy — i jest, o ile
nic w środku nie dotyka bazy.

Liczby na dowód: **43 testy, 664 asercje, 28 ms**. Cała reszta suite'u (139 testów) idzie
5 sekund, bo tam jest już MariaDB.

Podział odpowiedzialności:

| | `RoundRobinScheduler` | `FixtureGenerator` |
| --- | --- | --- |
| wie o | liczbach | encjach, transakcjach, blokadach |
| testowany | `TestCase`, bez kernela | `WebTestCase`, przez HTTP |
| co sprawdza test | arytmetykę | trwałość i 409 |

### 4.2 Metoda okręgowa

Jeden klub **przypięty**, pozostałe rotują wokół niego. Przy `n` klubach jest `n-1` kolejek, a
w każdej przypięty gra z tym, kto wrotował na pierwszą pozycję, a reszta paruje się od końców
do środka:

```php
if (0 === $index) {
    return [$slots[$pinned], $slots[$round % $rotating]];
}

return [
    $slots[($round + $index) % $rotating],
    $slots[($round - $index + $rotating) % $rotating],
];
```

Nieparzysta liczba klubów: dokładany jest **placeholder**, z którym nikt nie gra. Klub
wylosowany przeciw niemu po prostu pauzuje. Kolejek nadal jest `n`, bo każdy musi mieć wolny
tydzień — i test sprawdza, że każdy ma go **dokładnie raz**, inaczej ktoś zagrałby mniej
meczów i tabela byłaby kłamstwem.

### 4.3 Błąd, który znalazł test

Najciekawsza rzecz w tym etapie.

Napisałem oczywistą regułę stron: `swap = (round + index) % 2`. Wygląda rozsądnie — naprzemienna,
zależna od kolejki i pozycji. Test `testHomeAndAwayAreShared` od razu wywalił:

```
Club 1 hosts too rarely.
Failed asserting that 0 is equal to 4 or is greater than 4.
```

**Klub o najmniejszym identyfikatorze nie grał u siebie ani razu w całym sezonie.** Powód: po
posortowaniu ten klub siedzi na `$slots[0]`, a obie pozycje, na których może się znaleźć, wypadają
na tej samej parzystości — więc reguła zawsze stawiała go po tej samej stronie.

I nic innego w terminarzu nie wyglądało źle: 66 meczów, 11 kolejek, każda para raz, nikt dwa razy
w kolejce. Bez asercji na rozkład gospodarzy to by przeszło.

Zamiast zgadywać poprawkę, **zmierzyłem** sześć kandydatów dla 4–20 klubów i wybrałem najlepszego:

| Reguła | najgorsze odchylenie | zakres meczów u siebie |
| --- | --- | --- |
| `(round+i) % 2` | 9,5 | **0**..18 |
| `round % 2` | 1,5 | 0..2 |
| `i % 2` | 9,5 | 9..19 |
| **`i==0 ? round%2 : i%2`** | **0,5** | idealnie |

Wybrana reguła daje każdemu klubowi pół meczu od równego podziału: 5 albo 6 z 11 przy dwunastu
klubach (11 nie dzieli się na pół), a w dwumeczu **dokładnie 11 z 22** — potwierdzone też na
prawdziwych danych przez API.

Test został po tym **zacieśniony**: asercja to teraz `abs(home - played/2) <= 0.5` dla każdego
klubu i każdej liczby klubów od 2 do 16. Luźny próg („między 4 a 7") przepuściłby połowę błędów.

Uzasadnienie reguły, już po fakcie: przypięta para alternuje po kolejce, bo przypięty klub jest w
każdej kolejce i nic innego się dla niego nie zmienia; reszta alternuje po **pozycji w kolejce**,
bo klub obraca się przez wszystkie pozycje.

### 4.4 Blokada wiersza, nie sprawdzenie

`FixtureGenerator::generate()`

```php
$locked = $this->entityManager->find(Season::class, $season->getId(), LockMode::PESSIMISTIC_WRITE);
```

Naiwna wersja to `if ($fixtures->seasonHasFixtures($season)) { throw ... }`. Dwóch operatorów
klikających „Generuj" w tej samej sekundzie **obaj** nie znajdą wtedy meczów, obaj zbudują
terminarz i obaj zaczną wstawiać. Unikalny indeks odrzuci drugi wstaw — ale **w połowie**,
zostawiając pół terminarza i pięćsetkę.

`SELECT … FOR UPDATE` na wierszu sezonu sprawia, że drugie żądanie **czeka**, po zwolnieniu blokady
widzi mecze zapisane przez pierwsze i odmawia czysto: 409 `fixtures_already_generated`.

Warto wiedzieć, co ta blokada blokuje: **wiersz sezonu**, nie tabelę meczów. Wystarcza, bo każdy
generator najpierw sięga po ten sam wiersz — to jest wzorzec „zablokuj rodzica, żeby serializować
zapisy do dzieci".

Kasowanie terminarza jest **osobnym, świadomym** żądaniem (`DELETE .../fixtures`), i właśnie
dlatego `generate` może odmawiać zamiast po cichu podmieniać. Regeneracja to dwa kliknięcia, z
czego jedno z ostrzeżeniem.

### 4.5 Determinizm

`sort($teamIds)` na wejściu. Ten sam zestaw klubów **zawsze** daje ten sam terminarz, niezależnie
od kolejności, w jakiej wiersze wróciły z bazy.

Generator, którego wyjście zależy od kolejności wiersza w wyniku, jest niemożliwy do
zrozumienia („dlaczego dziś wyszło inaczej?") i niemożliwy do przetestowania dwa razy. Jest na to
osobny test, który porównuje terminarz dla `[1..6]` i dla `[5,3,6,1,4,2]`.

### 4.6 Pułapki Stage 4

**`array_values()` po `sort()`** — `sort()` przenumerowuje klucze w miejscu, więc `array_values`
nic nie robi. PHPStan to widzi.

**PHPStan zna PHPUnit.** `assertSame(count($x), count(array_unique($x)))` dostało uwagę, żeby użyć
`assertCount` — dzięki `phpstan/phpstan-phpunit`. Drobiazg, ale to ta klasa podpowiedzi, dla której
warto mieć rozszerzenia PHPStana.

**`hour: '2-digit'` w `Intl.DateTimeFormat`** renderuje w locale 12-godzinnym `03:00 PM`. Wiodące
zero na zegarze 12-godzinnym czyta się jak błąd. `hour: 'numeric'` daje `3:00 PM` albo `15:00`,
zależnie od czytelnika — format zostaje jego, nie nasz.

**Podmiana `node_modules` pod działającym dev serverem.** W trakcie tego etapu w `frontend/`
uruchomiło się `pnpm install`, co przebudowało `node_modules` na układ pnpm i zostawiło
`pnpm-lock.yaml` obok zacommitowanego `package-lock.json`. Objawy były mylące: `tsc` nie mógł
znaleźć własnego binarium, a po naprawie (`npm ci`) Vite dalej podawał **nieświeże moduły** —
`does not provide an export named 'SeasonPage'` dla pliku, który ten eksport ma. Lekarstwo:
restart dev servera i `rm -rf node_modules/.vite`.

Sedno nie leży w tym, który menedżer jest lepszy, tylko w tym, że **dwa lockfile'e w jednym
projekcie to błąd konfiguracji**: układ `node_modules` na dysku przestaje odpowiadać
któremukolwiek z nich, a narzędzia zgłaszają to jako brakujące eksporty i znikające binaria —
objawy, które nie wskazują na przyczynę. Projekt przeszedł potem na pnpm świadomie i w
całości: pole `packageManager` przypina wersję, `pnpm-lock.yaml` jest jedynym lockfile'em,
a CI, Dockerfile i README mówią jednym głosem. Patrz [D.7](#d7-jeden-menedżer-pakietów).

### Pytania na rozmowę — Stage 4

**Dlaczego algorytm nie zna Doctrine?**
Bo błąd w terminarzu objawia się tygodnie później i jedyna obrona to test wyczerpujący, a taki
test musi być natychmiastowy. 43 testy w 28 ms kontra 5 sekund z bazą — przy tej pierwszej
liczbie testuje się każdą liczbę klubów od 2 do 16, przy drugiej wybiera się jedną.

**Po co `SELECT … FOR UPDATE`, skoro jest unikalny indeks?**
Indeks zapewnia poprawność, ale w najgorszym momencie: odrzuca *w trakcie* wstawiania, zostawiając
pół terminarza i 500. Blokada zamienia wyścig w kolejkę — drugie żądanie czeka, widzi wynik
pierwszego i odmawia czysto 409-ką.

**Czemu `generate` odmawia, zamiast nadpisać?**
Bo wyniki mogą już wisieć na meczach z pierwszego terminarza. Nadpisanie zostawiłoby je wskazujące
na nieistniejące spotkania. Kasowanie jest osobnym żądaniem z ostrzeżeniem, więc regeneracja jest
możliwa, ale nigdy przypadkowa.

**Skąd wiadomo, że gospodarze rozkładają się równo?**
Z pomiaru, nie z rozumowania. Sześć kandydatów na regułę stron, każdy sprawdzony dla 4–20 klubów;
pięć z nich zostawiało jakiś klub na zero meczów u siebie. Test asertuje teraz odchylenie ≤ 0,5 od
połowy rozegranych meczów, więc kolejna „oczywista" zmiana tej reguły natychmiast zapali się na
czerwono.

---

## Stage 5 — mecze, maszyna stanów i zdarzenia

### 5.1 Dlaczego nie ma encji Match

Zacznijmy od faktu, który rozstrzyga sprawę zanim zacznie się dyskusja o modelowaniu:

```
$ php -l -r 'class Match {}'
PHP Parse error: syntax error, unexpected token "match", expecting identifier
```

**`Match` jest słowem zastrzeżonym w PHP 8.** `match` to wyrażenie językowe, a nazwy klas są
niewrażliwe na wielkość liter — więc `class Match {}` się nie kompiluje. Encja musiałaby się
nazywać `GameMatch`, `MatchRecord` albo podobnie, czyli nie tak, jak się nazywa.

Ale nawet gdyby PHP na to pozwalał, podział byłby artefaktem. Aplikacja, którą to zastępuje,
miała `Fixture` i `Match` w relacji jeden-do-jednego. Mecz i spotkanie to **to samo zdarzenie w
dwóch momentach** — przed gwizdkiem i po nim. Rozdzielenie kupuje joina przy każdym odczycie i
krok operatora („utwórz mecz"), który nie znaczy nic dla kogoś prowadzącego ligę.

Więc `Fixture` dostał `status`, wynik i dwa znaczniki czasu. Zniknął jeden join, jeden endpoint
i jeden krok w interfejsie.

### 5.2 Maszyna stanów jako dane

`backend/src/Entity/MatchStatus.php`

```php
return match ($this) {
    self::Scheduled => [self::Live, self::Cancelled, self::Postponed],
    self::Postponed => [self::Scheduled, self::Live, self::Cancelled],
    self::Live      => [self::Finished, self::Cancelled, self::Postponed],
    self::Finished, self::Cancelled => [],
};
```

Cała reguła w jednym miejscu, jako **tablica**, a nie rozsypane `if`-y po serwisie. Trzy zyski:

1. da się przeczytać w całości,
2. da się przetestować **wyczerpująco** — test sprawdza wszystkie **25** kombinacji, nie te
   kilka, o których ktoś pamiętał,
3. da się **wysłać klientowi**, żeby przycisk był wyłączony zamiast rozczarowywać.

Detal, który łatwo przeoczyć: `POSTPONED` **nie jest** stanem końcowym. Mecz odwołany z powodu
deszczu musi móc wrócić do kalendarza, inaczej ginie na cały sezon.

I test, którego brak byłby cichą dziurą: **żaden stan nie przechodzi w samego siebie.** Bez tego
„rozpocznij" na trwającym meczu wyglądałoby jak nieszkodliwy no-op, a przycisk nigdy by się nie
wyłączył.

Specyfikacja w teście jest **wypisana ręcznie**, nie wyprowadzona z enuma. Test, który pyta kod,
jakie są reguły, zgadza się z każdym błędem, jaki kod ma.

### 5.3 Dlaczego nie symfony/workflow

Bundle jest dobrym narzędziem, gdy przejścia mają strażników, listenery i metadane — zamówienie,
które wysyła maila do magazynu. Tutaj **cała maszyna to dziewięć linijek powyżej**. Workflow
dołożyłby plik konfiguracyjny, usługę, słownictwo i warstwę pośrednią, żeby te dziewięć linijek
zastąpić.

Warto wiedzieć, że istnieje, i umieć powiedzieć, dlaczego go nie ma. To jest częstsze pytanie
rekrutacyjne niż „jak skonfigurować workflow".

### 5.4 Gol i wynik w jednej transakcji

To jest najważniejsza linijka całego etapu.

Gol to **dwie** zmiany: wiersz zdarzenia i podbicie wyniku. Zapisane osobno, awaria między nimi
zostawia wynik, który nie zgadza się z własną historią — i **nic w aplikacji nigdy by tego nie
zauważyło**, bo obie liczby z osobna wyglądają sensownie.

```php
return $this->entityManager->wrapInTransaction(function () { … });
```

Stąd też decyzja, że zdarzenia są **tylko do dopisywania**: nie ma PATCH ani DELETE. Skoro wynik
jest wyprowadzony z tych wierszy, edytowalne zdarzenie to wynik, który może po cichu przestać
pasować do swojej historii. Pomyłkę poprawia się dopisując prawdę — tak jak działa notes
sędziego.

### 5.5 409 kontra 422

Rozróżnienie, które warto umieć wypowiedzieć:

| | znaczy | tutaj |
| --- | --- | --- |
| **422** | „popraw dane" | zawodnik spoza składu, klub spoza meczu, minuta 200 |
| **409** | „świat nie jest w tym stanie" | zakończenie meczu, który się nie zaczął; gol przed gwizdkiem |

Zakończenie meczu to **doskonale poprawne** żądanie — po prostu nie dla meczu, który jeszcze nie
wystartował. Nic w payloadzie nie jest złe, więc 422 byłoby kłamstwem.

Komunikat mówi, **co wolno**, żeby klient, który wypadł z synchronizacji, mógł się pozbierać bez
zgadywania:

```
Cannot go from scheduled to finished. Allowed from here: live, cancelled, postponed.
```

### 5.6 Zegar z wstrzyknięcia

`MatchLifecycle` bierze `Psr\Clock\ClockInterface`, nigdy `new DateTimeImmutable()`.

To nie ceremonia. Zadanie przypomnień w Stage 7 musi odpowiedzieć na pytanie „które mecze
zaczynają się za mniej więcej 24 godziny", a jedyny rozsądny sposób przetestowania tego to
**zamrożenie zegara** (`MockClock`). Sięgnij po prawdziwy czas w kodzie, a test staje się
`sleep`em.

Drugi detal, tego samego rodzaju: wznowienie przełożonego meczu **zachowuje** pierwotny gwizdek
(`$fixture->getStartedAt() ?? $now`), bo każda zapisana minuta jest od niego liczona. Ale
odesłanie meczu z powrotem do kalendarza **czyści** go — inaczej przełożony mecz wyglądałby na
rozegrany.

### 5.7 Serwer mówi, co wolno

`FixtureResource` niesie `allowed_transitions` — listę przejść, które serwer przyjąłby *teraz*.

Frontend **czyta tę listę**, zamiast reimplementować maszynę:

```tsx
{fixture.allowed_transitions.map((target) => <Button …>{TRANSITION_LABEL[target]}</Button>)}
```

Dzięki temu przyciski nie mogą się rozjechać z regułami, bo klient nie ma własnej kopii reguł.
To jest ta sama zasada co przy `fields` w kopercie błędów: serwer jest źródłem prawdy, klient
tylko go renderuje.

### 5.8 Pułapki Stage 5

**Test z błędnym założeniem, nie kod z błędem.** `?status=SCHEDULED,LIVE` zwracało 1 zamiast 2 —
bo przy **dwóch** klubach kalendarz ma dokładnie **jeden** mecz. Filtr działał; to test pytał o
niemożliwe. Warto zauważyć różnicę, zanim zacznie się „naprawiać" poprawny kod.

**Wartości domyślne kolumn przy ALTER TABLE.** Dodanie `status` do istniejącej tabeli wymaga
`options: ['default' => 'SCHEDULED']`, inaczej migracja wywala się na wierszach, które już tam
są. To samo dla wyników (`default => 0`).

**`@return list<T>` po zmianie sygnatury.** Dodanie parametru do `findForSeason()` przepisało
docbloc i PHPStan natychmiast zauważył brak typu elementu tablicy.

**Żółty i czerwony tylko na kartki.** Te dwa kolory pojawiają się w całej aplikacji **wyłącznie**
w `MatchTimeline` — dokładnie po to, żeby żółta kartka nigdy nie została odczytana jako
ostrzeżenie interfejsu. Status meczu ma pięć **strukturalnie** różnych traktowań (pulsująca
kropka, wypełnienie, przekreślenie, kreska z lewej), nie pięć kolorów: każde czyta się poprawnie
w skali szarości.

### Pytania na rozmowę — Stage 5

**Dlaczego nie ma encji `Match`?**
Bo `Match` to słowo zastrzeżone w PHP 8 i `class Match {}` się nie kompiluje. Ale niezależnie od
tego podział na `Fixture` i `Match` byłby artefaktem: to to samo zdarzenie przed gwizdkiem i po
nim, a relacja jeden-do-jednego kosztowałaby joina przy każdym odczycie i krok „utwórz mecz",
który nic nie znaczy.

**Czemu gol i wynik muszą być w jednej transakcji?**
Bo to dwie zmiany opisujące jeden fakt. Rozdzielone, awaria między nimi zostawia wynik
niezgodny z własną historią — i nic tego nie wykryje, bo obie liczby osobno wyglądają
poprawnie. Z tego samego powodu zdarzenia są tylko do dopisywania.

**Kiedy 409, a kiedy 422?**
422 mówi „popraw dane", 409 mówi „świat nie jest w tym stanie". Zakończenie meczu to poprawne
żądanie — po prostu nie dla meczu, który się nie zaczął, więc 422 byłoby kłamstwem o payloadzie.

**Po co `ClockInterface` zamiast `new DateTimeImmutable()`?**
Żeby dało się zamrozić czas w teście. Bez tego test reguły „przypomnij 24 godziny przed
gwizdkiem" musiałby albo czekać, albo nie istnieć.

**Skąd frontend wie, które przyciski wyłączyć?**
Z odpowiedzi serwera — `allowed_transitions`. Reimplementacja maszyny po stronie klienta
oznaczałaby dwie kopie reguł, które prędzej czy później się rozjadą; czytanie listy oznacza
jedną.

---

## Stage 6 — tabela, statystyki i dane demonstracyjne

### 6.1 Tabela nie jest przechowywana

Nie ma encji `Standing`, nie ma kolumny `points` na `season_teams` i nie ma nasłuchiwacza,
który po zakończeniu meczu dopisuje punkty. Tabela powstaje z wyników **przy każdym żądaniu**
(`src/Domain/Standings/StandingsCalculator.php`).

Uzasadnienie jest jedno i wystarcza: **przechowywana tabela to druga kopia prawdy.** W chwili,
w której rozjedzie się z meczami, z których powstała, nie ma jak stwierdzić, która z nich
kłamie. A rozjechać się może na wiele sposobów — poprawka wyniku, mecz cofnięty do LIVE,
błąd w nasłuchiwaczu, transakcja, która przeszła w połowie.

Kosztem są trzy zapytania na odsłonę. Przy dwunastu klubach i 132 meczach to nic; gdyby kiedyś
zaczęło boleć, cache'owanie **wyniku** jest odwracalne i nie wymaga zmiany modelu — a
denormalizacja wymaga.

### 6.2 Dlaczego dwa zapytania, a nie jedno

Mecz trzyma jeden klub w `home_team_id`, a drugi w `away_team_id`. Jedno `GROUP BY` widzi więc
klub wyłącznie z jednej strony boiska. „Sezon tego klubu" w jednym zgrupowanym zapytaniu
wymagałby `UNION`, którego DQL nie ma.

Sezon przyjeżdża zatem jako **przebieg po stronie gospodarzy i przebieg po stronie gości**, a
sumowanie dzieje się w PHP (`FixtureRepository::seasonAggregates()`). Warto o tym wiedzieć,
zanim się zobaczy dwa podobne zapytania i uzna je za powielenie.

Test na to jest w `StandingsTableTest::testHomeAndAwayAggregatesAreAddedTogether` i jest tam
nieprzypadkowo: **gdyby połówki się nie sumowały, każdy klub pokazywałby pół sezonu — a tabela
i tak wyglądałaby całkowicie wiarygodnie.** To jest ta klasa błędu, której się nie zauważa.

Agregaty wracają z PostgreSQL-a jako **tekst**, bo `COUNT` i `SUM` to `bigint`, a sterownik
nie ma go jak oddać liczbą. Rzutowanie siedzi na granicy repozytorium, żeby wszystko powyżej
liczyło na intach.

### 6.3 Remisy trzeba rozstrzygnąć do końca

Kolejność to punkty, różnica bramek, bramki zdobyte, **nazwa, id**. Dwa ostatnie kryteria nie
są regułami, o które ktokolwiek gra — są po to, żeby to samo żądanie dwa razy dało tę samą
tabelę.

Bez nich baza ma prawo zwrócić którykolwiek z klubów remisujących jako pierwszy, a tabela,
która przy odświeżeniu zmienia kolejność, **czyta się jak zepsuta, choć każda liczba w niej
jest poprawna**. To ta sama zasada co whitelistowane sortowanie list w Stage 3a.

### 6.4 Tabela czeka na gwizdek, lista strzelców nie

Do tabeli liczą się wyłącznie mecze `FINISHED`. Do statystyk zawodników — `LIVE` **i**
`FINISHED` (`MatchStatus::countedInStatistics()`).

To nie jest niekonsekwencja, tylko odwzorowanie prawdziwych rozgrywek: punkty przyznaje się na
koniec meczu, a gol w 60. minucie jest golem od razu. Lista strzelców rusza się w trakcie
meczu, tabela nie. Test `testGoalsInAMatchInProgressDoCountTowardsThePlayerStatistics` pilnuje
obu połówek tej reguły naraz.

Mecze `CANCELLED` i `POSTPONED` nie liczą się nigdzie — nawet jeśli zdążono w nich zapisać
zdarzenia przed odwołaniem. Mecz, który się nie odbył, nie jest niczyim dorobkiem.

### 6.5 Czego w statystykach nie ma

Nie ma kolumny „występy" i to jest decyzja, nie przeoczenie. Model nie zapisuje, kto wyszedł
na boisko — zdarzenie powstaje dopiero wtedy, gdy zawodnik **coś zrobił**. Liczenie meczów, w
których zawodnik ma zdarzenia, pokazałoby obrońcy, który nie strzelił i nie dostał kartki,
zero występów.

Kolumna, która dla większości składu podaje nieprawdę, jest gorsza niż kolumna, której nie
ma. Występy poczekają na model wyjściowej jedenastki.

### 6.6 Seeder gra przez prawdziwe serwisy

`app:seed:demo` nie wpisuje wyników do kolumn. Każdy mecz jest **rozpoczynany, jego bramki
zapisywane pojedynczo, a potem kończony** — przez te same `MatchLifecycle` i
`MatchEventRecorder`, których używa API.

Dzięki temu seeder jest ćwiczeniem reguł domenowych, a nie obejściem ich. Gol, który przestał
ruszać wynik, przejście, które przestało być dozwolone, albo walidacja składu, która zaczęła
odrzucać własnych zawodników — wszystko to **wywala tę komendę**, zanim wywali produkcję.

Skutek uboczny, który się liczy: dane demonstracyjne są z definicji spójne, bo powstały tą
samą drogą co dane prawdziwe.

### 6.7 Determinizm i idempotencja

Dwie własności ważniejsze od samych danych.

**Determinizm.** Losowość pochodzi z `Randomizer` na zaseedowanym `Mt19937`, więc ta sama
komenda daje tę samą ligę na każdej maszynie. Demo, którego tabela zmienia się przy każdym
uruchomieniu, **nie nadaje się do sprawdzania tabeli** — nie ma z czym porównać. Sprawdzone
wprost: przebudowa z `--flush` dała tabelę identyczną co do punktu.

**Idempotencja.** Drugie uruchomienie bez `--flush` jest no-opem, a nie drugą ligą obok
pierwszej. Seeder, który powiela własny wynik, nie może trafić do skryptu startowego.

Jedna rzecz celowo **nie** jest deterministyczna: hasło konta właściciela. Stałe hasło w
publicznym repozytorium jest stałym hasłem na każdym wdrożeniu, które kiedykolwiek tę komendę
uruchomi. Dane są powtarzalne, poświadczenie nie.

Uwaga o `EntityManager::clear()`: flushowanie partiami jest, `clear()` świadomie nie ma.
Odłączenie encji zwolniłoby pamięć, której ta komenda nie potrzebuje, a składy zebrane wyżej
muszą pozostać zarządzane dla meczów rozgrywanych niżej. Nawyk wart znania, nie wart
kopiowania tam, gdzie kupuje realny błąd za wyobrażoną oszczędność.

### 6.8 Pułapki Stage 6

**`public const int` to PHP 8.3.** Drugi raz w tym projekcie — typowane stałe klasowe nie
istnieją w 8.2 i kończą się błędem parsowania.

**`Randomizer::getFloat()` też jest z 8.3.** Ta pułapka jest gorsza, bo `php -l` jej nie
widzi: kod się parsuje i wywala dopiero przy wywołaniu. Ułamek powstaje więc z losowania
liczby całkowitej.

**Każde żądanie w teście funkcjonalnym restartuje kernel.** Encje utworzone w `setUp` należą
do `EntityManager`-a, który wtedy istniał; podane późniejszemu menedżerowi wyglądają jak nowe
wiersze i `flush()` odmawia. `MatchApiTest` tego nie widział, bo tworzy mecz raz, przed
pierwszym żądaniem. Rozwiązanie: **id przeżywa restart**, więc encje trzeba odszukać na nowo.

**`array_values()` na liście.** Trzeci raz. PHPStan łapie to za każdym razem.

**Test, który nie może paść.** `assertSame(3, StandingsTable::POINTS_FOR_A_WIN)` porównuje
literał ze stałą o tej samej wartości — PHPStan zgłasza to jako zawsze prawdziwe i ma rację.
Taki test nie chroni przed niczym; usunięty.

**PHPStan potrzebuje ciepłego kontenera.** `config/reference.php` odwołuje się do klas
`Symfony\Config\*`, które powstają w `var/cache`. Po eksperymentach z `APP_ENV=prod` cache
był niepełny i analiza sypnęła błędami w pliku, którego nie tknąłem. `cache:warmup` naprawia;
CI tego nie widzi, bo zawsze startuje na zimno i sam rozgrzewa cache.

### Pytania na rozmowę — Stage 6

**Dlaczego tabela nie jest zapisana w bazie?**
Bo byłaby drugą kopią prawdy. Kiedy rozjedzie się z meczami, z których powstała, nie ma jak
ustalić, która wersja jest poprawna. Wyliczanie kosztuje trzy zapytania na odsłonę, a jeśli
kiedyś zaboli, cache'owanie wyniku jest odwracalne — denormalizacja nie.

**Czemu sezon liczy się dwoma zapytaniami?**
Bo mecz trzyma dwa kluby w dwóch kolumnach, więc jedno `GROUP BY` widzi klub tylko z jednej
strony. Bez `UNION`, którego DQL nie ma, sezon przyjeżdża w dwóch kawałkach i sumuje się w
PHP. Gdyby ktoś zapomniał je zsumować, każdy klub pokazywałby pół sezonu — i nikt by tego nie
zauważył, bo tabela dalej wyglądałaby sensownie.

**Po co rozstrzygać remis po nazwie i id, skoro nikt tak nie gra?**
Żeby to samo żądanie dwa razy dało tę samą kolejność. Bez tego baza może zwrócić remisujące
kluby w dowolnej kolejności, a tabela zmieniająca układ przy odświeżeniu wygląda na zepsutą,
choć każda liczba w niej jest poprawna.

**Dlaczego gol w trwającym meczu liczy się do listy strzelców, a nie do tabeli?**
Bo punkty przyznaje się na koniec meczu, a gol jest golem od razu. Tak działają prawdziwe
rozgrywki i tak samo zachowuje się aplikacja. To nie niekonsekwencja, tylko dwie różne reguły
opisujące dwie różne rzeczy.

**Co daje to, że seeder gra przez serwisy domenowe zamiast wpisywać wyniki?**
Zamienia go w test. Gol, który przestał ruszać wynik, przejście, które przestało być
dozwolone, walidacja składu, która zaczęła odrzucać własnych zawodników — każda z tych regresji
wywala komendę. Przy okazji dane demonstracyjne są spójne z definicji, bo powstały tą samą
drogą co prawdziwe.

**Po co seedowi determinizm?**
Żeby dało się go użyć do sprawdzenia czegokolwiek. Demo, którego tabela zmienia się przy
każdym uruchomieniu, nie ma punktu odniesienia — nie da się powiedzieć „tu powinno być 25
punktów". Hasło właściciela jest wyjątkiem i celowo losowe: stałe hasło w publicznym
repozytorium to stałe hasło wszędzie, gdzie tę komendę odpalono.

---

## Stage 7 — Messenger, powiadomienia i harmonogram

### 7.1 Transport w bazie, i po co

Kolejka stoi na transporcie **Doctrine**, nie na Redisie ani AMQP. Powód nie jest operacyjny,
tylko transakcyjny i jest wart wyłożenia w całości, bo to najciekawsza rzecz w tym etapie.

`dispatch()` na tym transporcie to `INSERT` do `messenger_messages` — **na tym samym
połączeniu**, wewnątrz tej samej transakcji, co zmiana, którą wiadomość ogłasza. W efekcie:

```php
$this->entityManager->wrapInTransaction(function () use ($fixture, $target): void {
    $fixture->setStatus($target);
    $this->entityManager->flush();

    if (MatchStatus::Finished === $target) {
        $this->bus->dispatch(new MatchFinished((int) $fixture->getId()));
    }
});
```

Jeśli cokolwiek między tym `flush()` a zatwierdzeniem transakcji padnie, **wiadomość znika
razem z wynikiem, który ogłaszała**. Nie ma okna, w którym świat został poinformowany o
rezultacie, którego baza nie ma.

Broker poza bazą tego nie potrafi. Wysyłka przed commitem grozi powiadomieniem o wyniku, który
nigdy nie wylądował; wysyłka po commicie grozi wynikiem, o którym nikt się nie dowie, bo proces
umarł w międzyczasie. Django rozwiązuje to osobnym mechanizmem — `transaction.on_commit`; tutaj
rozwiązuje to samo miejsce przechowywania kolejki, **strukturalnie**.

Twierdzenie tej rangi nie może zostać w komentarzu, więc `MessengerTransactionTest` sprawdza
je z obu stron: commit zostawia wiersz, rollback go zabiera — czytając `messenger_messages`
wprost, a nie pytając Messengera, co jego zdaniem zrobił.

Cena jest uczciwa i warto ją nazwać: to kolejka w relacyjnej bazie. Odpytuje ją, nie rozgłasza
do innych usług, i przy kilku tysiącach wiadomości dziennie to bez znaczenia. Liga, która
potrzebowałaby stu tysięcy, podmieniłaby DSN i **świadomie oddała** tę gwarancję.

### 7.2 Wiadomość niesie identyfikator

`MatchFinished` ma jedno pole: `int $fixtureId`. Nigdy encji.

Dwa powody, oba praktyczne. Wiadomość jest serializowana do wiersza i odczytywana później,
możliwe że przez proces, który wystartował po zakończeniu tego, który ją wysłał — a encja
Doctrine niesie proxy i mapę tożsamości, które w tamtym procesie nic nie znaczą. Drugi: zanim
wiadomość zostanie obsłużona, wiersz mógł się zmienić albo zniknąć, a przewożenie migawki
pozwoliłoby handlerowi zadziałać na meczu, który już tak nie wygląda — po cichu.

Wiadomość mówi więc, **co się stało i któremu wierszowi**; handler czyta prawdę sam.

### 7.3 „Co najmniej raz" i klucz deduplikacji

Kolejka obiecuje dostarczenie **co najmniej raz**, nie dokładnie raz. Worker, który umrze
między wykonaniem pracy a potwierdzeniem wiadomości, dostanie ją ponownie. To nie jest usterka
do naprawienia — to jest kontrakt.

Odpowiedzią jest kolumna `dedupe_key` z unikalnym indeksem i trzy mechanizmy, z których **żaden
sam nie wystarcza**:

1. **Klucz opisuje fakt, nie dostarczenie.** `MATCH_FINISHED:41:7` znaczy „użytkownik 7 został
   powiadomiony o meczu 41". Ponowne wykonanie handlera policzy ten sam klucz. Celowo nie ma w
   nim znacznika czasu ani identyfikatora wiadomości — te zmieniają się między dostarczeniami
   tego samego faktu, czyli robią dokładnie to, czego klucz robić nie może.
2. **Unikalny indeks.** Jedyna część, której nie da się przegrać w wyścigu. Dwa workery
   obsługujące tę samą wiadomość w tej samej chwili przejdą sprawdzenie z punktu 3; baza
   pozwoli wygrać dokładnie jednemu.
3. **Sprawdzenie przed zapisem.** Nie gwarancja — gwarancją jest indeks — ale zamienia
   przypadek typowy (ponowne dostarczenie po sekundach lub godzinach) w jedno zapytanie i zero
   zapisów, zamiast w nieudaną transakcję i ponowienie.

Gdy wyścig faktycznie zajdzie, `flush()` rzuci, wiadomość zostanie ponowiona, a przy ponowieniu
punkt 3 znajdzie wszystkie klucze i nie zrobi nic. **Awaria rozwiązuje się sama.**

### 7.4 Handler musi znieść, że świat się zmienił

Każdy wczesny powrót w `MatchFinishedHandler` opisuje sytuację, która wystąpi:

```php
if (null === $fixture) return;                                  // sezon skasowany
if (MatchStatus::Finished !== $fixture->getStatus()) return;    // mecz cofnięty i odwołany
```

Rzucenie wyjątkiem wysłałoby wiadomość na transport `failed` i poprosiło człowieka o obejrzenie
czegoś, co po prostu **przestało być prawdą**. To nie błąd — to upływ czasu.

Pokazane, a nie tylko opisane: po odbudowie danych demonstracyjnych w kolejce zostało 148
wiadomości, z czego połowa wskazywała na mecze usunięte przez `--flush`. Worker skonsumował
wszystkie, utworzył 74 powiadomienia i po pozostałych 74 nie został ślad.

### 7.5 Harmonogram w kodzie, nie w cronie

`#[AsSchedule('reminders')]` z `RecurringMessage::every('15 minutes', …)`.

Co to daje ponad wpis w crontabie: leży w repozytorium, jest przeglądane razem z kodem, który
uruchamia, wdraża się razem z nim i jest identyczne na każdej maszynie. Linia crontaba żyje na
jednym serwerze, edytuje ją ten, kto ma tam powłokę, i odkrywa się jej brak wtedy, gdy
przypomnienia przestają przychodzić.

Co kosztuje: musi chodzić proces. Jeśli nie chodzi, nic się nie odpala — ta sama awaria co
zatrzymany demon crona, tylko łatwiejsza do przeoczenia, bo nie wygląda na infrastrukturę.

**Okno ±15 minut** wokół „za dobę" jest dobrane do częstotliwości harmonogramu, żeby każdy mecz
wpadł dokładnie w jeden przebieg. Szersze — mecze przypominane dwa razy, co klucz deduplikacji
wchłonie, ale **po cichu**, ukrywając pomyłkę. Węższe — przebieg spóźniony o kilkanaście sekund
gubi mecze całkowicie, a tego nie wchłonie nic.

Ta sama logika siedzi w komendzie `app:matches:remind`, **tym samym obiektem**, nie kopią. Gdy
przypomnienia przestaną przychodzić, pierwsze pytanie brzmi „skan zepsuty czy worker nie
chodzi" — komenda odpowiada w jednej linii. Dwie implementacje rozjechałyby się, a rozjechałaby
się ta, której nikt nie uruchamia.

### 7.6 Worker: Windows i produkcja

**Lokalnie.** `messenger:consume` obsługuje sygnał zatrzymania przez `ext-pcntl`, którego na
Windowsie nie ma. Stąd `--time-limit`: bez niego jedynym sposobem na zatrzymanie workera jest
zabicie go, możliwie w połowie obsługi wiadomości. Druga uciążliwość: worker trzyma kontener, z
którym wystartował, więc **serwuje kod, który już zmieniłeś**. `messenger:stop-workers` kończy
go wcześniej, a `dev.ps1` uruchamia go razem z resztą — bo nic nie ostrzeże, że go brakuje:
wyniki się zapisują, dzwonek po prostu zostaje pusty.

**Na produkcji.** Osobna usługa workera to na Renderze plan płatny, więc worker chodzi w tym
samym kontenerze, obok serwera, uruchamiany z entrypointu w pętli, która go restartuje.

Ograniczenie warto nazwać wprost: darmowa instancja zasypia po kwadransie ciszy i worker zasypia
z nią. Zakolejkowana praca nie ginie — to wiersze w tabeli, odebrane przy następnym wybudzeniu —
ale **harmonogram posuwa się tylko wtedy, gdy ktoś korzysta z aplikacji**, więc przypomnienie
może przyjść spóźnione. Naprawa to osobna usługa zawsze włączona, czyli koszt, nie kod.

### 7.7 Pułapki Stage 7

**Usuwanie organizacji było zepsute od Stage 2.** `remove($organization)` zdawało się na kaskady
bazy, a zdarzenie meczowe wskazuje na strzelca z `ON DELETE RESTRICT` — celowo, żeby skasowanie
zawodnika nie wymazało jego goli. Kaskada dociera do zawodników i zdarzeń **dwiema różnymi
ścieżkami**, a baza może je wziąć w dowolnej kolejności, więc usunięcie czasem kasowało
zawodnika, gdy jego gole jeszcze istniały, i odrzucało całość. Gorzej: nie zawsze. Istniejący
test kasował organizację **pustą**, więc niczego nie widział.

Poprawka porządkuje kasowanie dzieci od najgłębszych, w jednej transakcji; `RESTRICT` dalej
broni przypadku, dla którego powstał. Nowy test sprawdzony przez chwilowe przywrócenie starej
implementacji — bez poprawki daje 500 z naruszeniem klucza obcego.

**Moja weryfikacja determinizmu w Stage 6 była nieważna.** Grepowałem wtedy linię, której w
wyjściu nie było, `--flush` przerywał się na tym samym błędzie klucza obcego, organizacja nie
znikała — i tabela wychodziła „identyczna", bo porównywałem dane same ze sobą. Powtórzone
poprawnie: maksymalne `id` meczu zmieniło się z 264 na 396, co dowodzi przebudowy, a tabela
wróciła identyczna.

**DQL nie wybierze samego aliasu z JOIN-a.** `SELECT u FROM OrganizationMembership m JOIN m.user u`
to błąd semantyczny, nie skrót. Zapytanie trzeba zbudować od encji, o którą naprawdę chodzi.

**Asercja na słowie jest asercją o ustawieniach maszyny.** Test `formatRelative` sprawdzał
`/minute/` i padał, bo `Intl` sformatował „3 minuty temu". Testowana jest **decyzja o jednostce
i liczbie**, a nie renderowanie zdania — więc oczekiwanie buduje się z tej samej pary, nie z
frazy.

**PHPStan i wygenerowany `config/reference.php`.** Ten plik deklaruje alias typu wymieniający po
jednej klasie konfiguracyjnej na włączony bundle, a te powstają per środowisko w
`var/cache/<env>/Symfony/Config`. Analiza przechodziła lub nie w zależności od tego, które cache
były rozgrzane — i nie doszedłem, czym dokładnie różni się od tego CI, gdzie zawsze było
zielono. Zamiast gonić różnicę wykluczyłem plik z analizy: to generowana pomoc dla IDE, nie kod
aplikacji, a check, którego wynik zależy od stanu cache'u, odpowiada na pytanie, którego nikt
nie zadał.

**Fabryka organizacji sama nadaje właściciela.** Dopisanie członkostwa OWNER w teście kończy się
naruszeniem unikalności — organizacja bez właściciela to wiersz, którego fabryka nie produkuje.

### Pytania na rozmowę — Stage 7

**Dlaczego kolejka stoi w bazie, a nie w Redisie?**
Bo `dispatch()` jest wtedy `INSERT`-em na tym samym połączeniu, więc wiadomość jest zatwierdzana
albo wycofywana razem ze zmianą, którą ogłasza. Broker poza bazą wymusza wybór: wysłać przed
commitem i ryzykować powiadomienie o czymś, co nie wylądowało, albo po commicie i ryzykować
zmianę, o której nikt się nie dowie. Cena to kolejka, która odpytuje i nie skaluje się dowolnie
— świadomie zapłacona przy tej wielkości.

**Co znaczy, że kolejka gwarantuje „co najmniej raz", i jak się z tym żyje?**
Że handler zostanie wywołany ponownie, jeśli worker padnie między wykonaniem pracy a
potwierdzeniem. Żyje się z tym, czyniąc handler idempotentnym: klucz opisujący fakt, unikalny
indeks jako jedyna niepodrabialna gwarancja, i sprawdzenie przed zapisem, żeby typowy przypadek
nie kosztował nieudanej transakcji.

**Dlaczego wiadomość niesie id, a nie encję?**
Bo jest serializowana i czytana później, możliwie przez inny proces, w którym proxy i mapa
tożsamości Doctrine nic nie znaczą — a przede wszystkim dlatego, że wiersz mógł się w
międzyczasie zmienić. Migawka pozwoliłaby zadziałać na stanie, którego już nie ma.

**Handler znajduje mecz, który nie jest już zakończony. Rzucić czy wyjść?**
Wyjść. Rzucenie wysyła wiadomość na transport `failed` i prosi człowieka o obejrzenie czegoś,
co po prostu przestało być prawdą. Wyjątki są od rzeczy, które da się naprawić ponowieniem;
upływ czasu do nich nie należy.

**Skąd wzięło się okno ±15 minut?**
Z częstotliwości harmonogramu. Ma być dokładnie tak szerokie, żeby każdy mecz wpadł w jeden
przebieg i żaden w dwa. Szersze chowa pomyłkę za kluczem deduplikacji, węższe gubi mecze przy
przebiegu spóźnionym o kilkanaście sekund.

**Po co komenda, skoro jest harmonogram?**
Żeby dało się odróżnić „skan jest zepsuty" od „worker nie chodzi" bez czekania kwadransa. I to
musi być ten sam obiekt, nie kopia logiki — dwie implementacje rozjadą się, a rozjedzie się ta,
której nikt nie uruchamia.

---

## Stage 8 — Realtime i hardening

### 8.1 Hub w tym samym procesie

FrankenPHP to Caddy z wkompilowanym PHP **i Mercure**. Sprawdzone, nie założone — w
`caddy/frankenphp/main.go` stoi `_ "github.com/dunglas/mercure/caddy"`. Realtime nie potrzebuje
więc drugiego kontenera, drugiego procesu ani drugiej rzeczy do wdrożenia. Ta decyzja zapadła
trzy etapy wcześniej, przy wyborze obrazu, i dopiero teraz się opłaciła.

Włączenie huba idzie przez `CADDY_SERVER_EXTRA_DIRECTIVES` — placeholder, który obraz zostawia
**wewnątrz swojego bloku strony**.

Pierwsza wersja wrzucała dyrektywę do `Caddyfile.d/mercure.caddyfile` i to był błąd: ten katalog
jest importowany na poziomie **globalnym** i oczekuje całych bloków stron, więc samotne
`mercure` zostało odczytane jako adres strony i Caddy w ogóle nie wstał. Komunikat był zresztą
wzorowy: *parsed 'mercure' as a site address, but it is a known directive*.

Złapało to CI w półtorej minuty — i to jest cały powód, dla którego obraz jest tam budowany
**i uruchamiany**, a nie tylko budowany. Przy okazji wyszło, że health check to za mało:
serwer, który nie wstał, nie odpowiada na nic, więc awaria wyglądała jak awaria aplikacji.
Doszło osobne sprawdzenie, że hub nasłuchuje — brak huba daje 404, hub bez tokenu daje 401, a
te dwie odpowiedzi da się rozróżnić.

`MERCURE_URL` liczy się w entrypoincie, bo port nadaje Render dopiero przy starcie. Aplikacja
publikuje sama do siebie po pętli zwrotnej; `MERCURE_PUBLIC_URL` to **ścieżka**, nie adres —
przeglądarka rozwiązuje ją względem origin, na którym już jest, więc nic nie musi znać nazwy
hosta wdrożenia.

### 8.2 Cienkie zdarzenia

Przez hub leci `{"fixture_id": 41}` i nic więcej. To decyzja, nie skrót.

**Autoryzacja zostaje w jednym miejscu.** Temat jest bytem gruboziarnistym: wolno go
subskrybować albo nie. REST API już rozstrzyga, per żądanie i per rola, co komu wolno zobaczyć.
Przepychanie meczu przez hub oznaczałoby utrzymywanie **drugiej, słabszej** odpowiedzi na to
samo pytanie — a dwie odpowiedzi się rozjeżdżają.

**Strumień nie może się zestarzeć.** Ładunek opisuje moment, sygnał opisuje fakt. Dwie
aktualizacje, które przyjdą nie po kolei, zostawiają klienta z dwoma pobraniami, a nie z
wyrenderowaniem starszego stanu.

Zysk to różnica między „w ciągu trzech sekund" a „natychmiast", nie oszczędność żądania.

### 8.3 Token na jeden temat, w ciasteczku

Hub nie wie nic o organizacjach, członkostwach ani rolach — sprawdza wyłącznie, czy token
subskrybenta wymienia żądany temat. Decyzja zapada więc tam, gdzie aplikacja i tak umie ją
podjąć: `FixtureScope` rozwiązuje mecz przez członkostwo dzwoniącego, więc obcy dostaje **404,
zanim jakikolwiek token powstanie**.

Token wymienia **dokładnie jeden temat**. Nie wildcard, nie sezon: token na tyle szeroki, żeby
był wygodny, przeżywa powód, dla którego został wydany. Test sprawdza to na roszczeniach JWT, a
nie na wywołaniu, które je zbudowało — i przy okazji, że nie ma w nim prawa `publish`.

Jedzie w ciasteczku httpOnly, z powodu praktycznego i z powodu bezpieczeństwa. `EventSource`
nie umie ustawić nagłówka żądania, więc token w ciele odpowiedzi musiałby wylądować w query
stringu, czyli w logach i w historii. A poza tym obowiązuje ten sam argument, który trzyma
refresh token poza zasięgiem JavaScriptu.

Bundle zresztą tego pilnuje: przy `MERCURE_PUBLIC_URL` wskazującym inną domenę drugiego poziomu
**odmawia wystawienia ciasteczka**. Złapało to przykładowy `example.com`, który został w `.env`
z recepty.

### 8.4 Publikacja przez kolejkę

Publikacja do huba to żądanie HTTP, którego **nie da się cofnąć**. Wysłanie go z serwisu, który
właśnie zmienił dane, oznaczałoby gola na czyimś ekranie, którego baza potem nie przyjęła.

Więc serwis wysyła `MatchUpdated` — wiadomość, wewnątrz transakcji, na transporcie Doctrine —
a publikuje dopiero handler. Kolejka daje tu semantykę „po zatwierdzeniu i tylko wtedy", której
hub nie ma. To ta sama gwarancja co w Stage 7, użyta w drugą stronę: tam chodziło o to, żeby
wiadomość zniknęła razem ze zmianą, tu o to, żeby skutek uboczny nastąpił **po** niej.

Koszt: opóźnienie workera, rzędu sekundy. Wciąż trzykrotnie mniej niż polling, i cena za to, że
ekran nigdy nie wyprzedzi bazy.

### 8.5 Degradacja jest funkcją, nie awarią

`useLiveMatch` ma jedną obietnicę: **nigdy nie zostawia wywołującego bez aktualizacji**. Flaga
wyłączona, endpoint tokenu odmawia, hub nieosiągalny, hub padł w połowie meczu — każdy z tych
przypadków kończy się `polling`, a timery ruszają z powrotem.

`EventSource` sam się wznawia, ale hub, który zniknął, zostawiłby stronę cicho nieaktualną na
czas prób. Dlatego `onerror` zamyka połączenie i wraca do timerów od razu.

Transport jest zwracany wyłącznie po to, żeby interfejs mógł go pokazać — w tooltipie, nie w
etykiecie, bo dla czytelnika to bez różnicy. Ma znaczenie dopiero, gdy ktoś zgłasza, że mecz
„przestał się odświeżać": pierwsze sensowne pytanie brzmi, na którym transporcie był.

### 8.6 Limit prób logowania

`login_throttling` na firewallu, nie w kontrolerze — authenticator działa, zanim jakikolwiek
kontroler dojdzie do głosu.

Limit jest per adres **i** per konto, co ma znaczenie w obie strony. Sam per konto pozwala
botnetowi rozłożyć próby po adresach; sam per adres blokuje całe biuro za jednym NAT-em, gdy
ktoś się pomyli.

Przekroczenie limitu to **429 z własnym kodem**, nie kolejne „invalid credentials". To jedyna
porażka logowania, którą warto odróżnić, i nic nie zdradza: mówi o częstotliwości prób z tego
adresu, a nie o tym, czy konto istnieje. Bez tego człowiek przepisuje hasło, które było
poprawne, a klient wali dalej.

### 8.7 Żądania warunkowe

ETag liczony z **bajtów, które i tak miały pójść**, nie z czegokolwiek o danych za nimi. To
słabsza wersja cache'owania — serwer wykonuje całą pracę i oszczędza wyłącznie transfer — i
wybrana świadomie: tag wyprowadzony z „kiedy ten sezon ostatnio się zmienił" wymaga czegoś, co
utrzyma ten znacznik w prawdzie, a nieaktualny serwuje złą odpowiedź z pełnym przekonaniem.
Ten nie może się pomylić: inne bajty, inny tag.

`private`, bo to są odpowiedzi o organizacjach konkretnej osoby — wspólny cache trzymający je
byłby wyciekiem, nie optymalizacją. Odpowiedzi ustawiające ciasteczko są pomijane: 304
zgubiłoby nagłówek, dla którego to żądanie istniało.

### 8.8 PHPStan 6 → 8

Sześć błędów na całym projekcie, wszystkie sensowne, żaden nieuciszony.

Dwa realne: `max()` na liście, która może być pusta (fatal, nie ostrzeżenie), i wywołanie
metody na `Profile|null`. Jeden wymusił nazwanie niezmiennika — `getUserIdentifier()` ma
zwracać niepusty łańcuch, więc teraz mówi to wprost i rzuca, zamiast zakładać. Trzy to
asercje, które nic nie sprawdzały, bo analizator już dowiódł ich prawdziwości.

Wniosek warty zapamiętania: poziom 8 nie jest ceremonią przy kodzie, który od początku ma
typy. Kosztował godzinę i znalazł dwa błędy, których żaden test nie szukał.

### 8.9 Pułapki Stage 8

**Nowy test potrafi zabić sto niepowiązanych.** Testy limitu logowania wyczerpały pulę adresu
`127.0.0.1`, z którego loguje się cały pakiet — 104 błędy w testach, których nie tknąłem.
Limiter liczy per adres, więc te testy dostały własne adresy z puli dokumentacyjnej.

**A licznik przeżywa uruchomienie pakietu**, bo siedzi w cache'u, którego `ResetDatabase` nie
dotyka. Test przechodził raz, a potem padał do końca minuty — i przechodził znowu później, bez
niczyjej zmiany. Czyszczenie puli przed testem załatwia sprawę; bez tego byłby to klasyczny
test „migający".

**Nazwa klasy wiadomości jest w `body`, nie w `headers`.** Domyślny serializer zapisuje
zserializowaną kopertę, więc liczenie wiadomości po typie robi się przez dopasowanie do ciała.
Sprawdzone empirycznie, po tym jak wariant z `headers` cicho zwracał zero.

**`assertCount` już zawęża typ.** PHPStan przyjął `assertNotEmpty($fixtures)` jako zbędne i
miał rację — pusta mogła być dopiero kolumna wyciągnięta z tej listy. Asercja poszła tam, gdzie
naprawdę coś mówi.

### Pytania na rozmowę — Stage 8

**Czemu przez hub leci sam identyfikator, a nie mecz?**
Bo temat jest gruboziarnisty — wolno go subskrybować albo nie — a REST API już rozstrzyga
dostęp per rola. Przepychanie danych przez hub to druga, słabsza odpowiedź na to samo pytanie,
a dwie odpowiedzi się rozjeżdżają. Do tego sygnał opisuje fakt, więc nie może się zestarzeć w
drodze.

**Dlaczego publikacja idzie przez kolejkę, a nie wprost z serwisu?**
Bo żądania HTTP nie da się wycofać. Wysłane z wnętrza transakcji, która potem padnie, zostawia
gola na ekranie i nic w bazie. Wiadomość na transporcie Doctrine wycofuje się razem ze zmianą,
więc skutek uboczny następuje po zatwierdzeniu i tylko wtedy.

**Token subskrybenta w ciasteczku czy w URL-u?**
W ciasteczku, httpOnly. `EventSource` nie ustawia nagłówków, więc alternatywą jest query
string, czyli logi i historia przeglądarki. Ten sam argument, który trzyma refresh token poza
JavaScriptem — i przy okazji wymusza jeden origin, czego bundle zresztą pilnuje.

**Co się dzieje, gdy hub padnie w trakcie meczu?**
Hook zamyka połączenie i wraca do pollingu; strona działa dalej, tylko wolniej. Realtime, które
psuje stronę, gdy hub znika, jest gorsze niż brak realtime — dlatego polling nigdy nie został
usunięty, tylko schowany za tym samym interfejsem.

**Jak liczysz ETag i dlaczego nie z daty modyfikacji?**
Ze skrótu bajtów odpowiedzi. Tag wyprowadzony ze znacznika czasu wymaga, żeby ktoś ten znacznik
utrzymywał w prawdzie, a pomyłka serwuje złą odpowiedź z przekonaniem. Skrót treści nie ma jak
skłamać — kosztuje za to całą pracę serwera, oszczędza wyłącznie transfer.

---

## Wdrożenie

### D.1 Dlaczego PostgreSQL

Projekt startował na MariaDB, bo taka jest w XAMPP-ie. Przy wdrożeniu okazało się to ślepą
uliczką: **darmowy, trwały hosting MySQL praktycznie nie istnieje**, a Render — który buduje
obrazy Dockera za darmo — oferuje wyłącznie Postgresa, i to takiego, który kasuje się po 30
dniach. Stąd układ skopiowany z poprzedniej aplikacji: **aplikacja na Renderze, baza na
Neonie**, gdzie darmowy projekt jest bezterminowy.

Zmiana kosztowała mniej, niż się wydaje, bo Doctrine abstrahuje zapytania. Encje, repozytoria
i DQL zostały bez zmian; przepisała się **tylko warstwa migracji** — sześć plików MySQL-owych
zastąpił jeden bazowy dla Postgresa. Zysk przy okazji: MariaDB 10.4 jest po EOL, co README
musiało uczciwie odnotowywać.

I jeden zysk konkretny, nie kosmetyczny — patrz niżej.

### D.2 Co znalazła zmiana bazy

Dwa testy, zielone od tygodni, popsuły się natychmiast. Oba słusznie.

**1. `LIKE` w PostgreSQL rozróżnia wielkość liter.**

W MySQL kolacja `utf8mb4_unicode_ci` sprawiała, że `LIKE '%nowak%'` trafiało w „Nowak" — za
darmo i bez niczyjej decyzji. Postgres porównuje bajty, więc wyszukiwarka po prostu przestała
działać dla innej wielkości liter.

Poprawka to `LOWER(kolumna) LIKE :search` z parametrem też zmniejszonym. Więcej pisania i
wersja uczciwsza: nieczułość na wielkość liter jest teraz **własnością zapytania**, a nie
przypadkiem konfiguracji kolumny.

**2. Indeks częściowy wykrył realny błąd kolejności zapisów.**

Na MariaDB regułę „najwyżej jeden kapitan na skład" pilnował wyłącznie kod, bo MariaDB nie ma
indeksów częściowych. PostgreSQL ma:

```php
#[ORM\UniqueConstraint(name: 'uniq_roster_one_captain', columns: ['season_team_id'], options: ['where' => 'captain'])]
```

Po dołożeniu go test przekazania opaski **od razu padł z naruszeniem unikalności**. Powód:
**Doctrine w jednym `flush()` wykonuje wszystkie INSERT-y przed wszystkimi UPDATE-ami.**
Wstawienie nowego kapitana i zdjęcie opaski poprzedniemu w tej samej jednostce pracy oznaczało
więc INSERT z `captain = true`, zanim stara flaga została wyczyszczona — czyli moment z dwoma
kapitanami.

Na MariaDB nie protestowało nic, bo nie było czym: stan końcowy wychodził poprawny, więc błąd
był niewidoczny. Poprawka to osobny `flush()` na degradację, przed promocją — obie wewnątrz tej
samej transakcji, więc przekazanie opaski nadal jest jednym aktem.

Morał wart zapamiętania: **ograniczenie w bazie znajduje błędy, których test nie szukał.**

Detal, który kosztował chwilę: Doctrine porównuje predykat indeksu częściowego **dosłownie**.
`'where' => '(captain)'` sprawia, że `doctrine:schema:validate` wiecznie widzi różnicę, bo
Postgres zapisuje go jako `captain`, bez nawiasów. Trzeba wpisać dokładnie to, co baza zwraca.

### D.3 Jeden obraz, jeden origin

`Dockerfile` w korzeniu buduje SPA w jednym etapie (`node:22-alpine`), a w drugim składa API na
**FrankenPHP** — czyli Caddym z wbudowanym PHP. Zbudowana SPA ląduje w `backend/public/`, więc
Caddy serwuje hashowane assety prosto z dysku, a czego nie znajdzie, wpada do PHP, gdzie
`SpaController` oddaje `index.html`.

Jeden origin to nie estetyka. **Refresh token jest ciasteczkiem o ścieżce `/api/v1/token`** — z
jednego hosta działa bez żadnych negocjacji CORS z poświadczeniami.

`SpaController` ma `priority: -1000` i wymaganie z negatywnym lookahead na `api/`, żeby nieznany
endpoint dalej odpowiadał **404 w kopercie JSON**, a nie HTML-em klientowi, który czeka na JSON.
Bez tego trasy SPA (`/dashboard`, wklejony link, F5) dawałyby 404 z serwera, który nigdy o nich
nie słyszał — aplikacja działałaby do pierwszego odświeżenia strony.

FrankenPHP wybrany także z myślą o Stage 8: ma **wbudowany hub Mercure**, więc realtime nie
będzie wymagał drugiego kontenera ani osobnego procesu.

### D.4 Klucze JWT nie mogą powstawać przy starcie

Najciekawsza pułapka całego wdrożenia.

Odruch mówi: wygeneruj parę kluczy w entrypoincie. **Nie wolno** — darmowa instancja Rendera
zasypia po piętnastu minutach ciszy, a każde wybudzenie to nowy kontener. Nowa para kluczy przy
każdym starcie oznacza, że **każdy odwiedzający wylogowuje poprzedniego**.

Drugi odruch: wypal klucze w obrazie. Też nie — prywatny klucz w buildzie publicznego
repozytorium to prywatny klucz w publicznym repozytorium. Do tego każdy deploy unieważniałby
wszystkie sesje.

Więc klucze przychodzą **z konfiguracji**, base64 (żeby przeżyły wklejenie w formularz), a
entrypoint zapisuje je do plików przed startem serwera. Dev i produkcja mają osobne pary —
klucz z `.env` nigdy nie jedzie na produkcję.

### D.5 Migracje z entrypointu

Render ma fazę pre-deploy, ale **jest płatna**, więc migracje odpalają się przy starcie, pod
flagą `RUN_RELEASE_ON_START`. To znaczy, że lecą przy **każdym wybudzeniu ze snu**, nie tylko
przy deployu — dlatego `migrate` musi być bezpieczne do powtarzania (jest) i dlatego jest
`--allow-no-migration`, żeby „nie ma nic do zrobienia" nie było traktowane jak błąd.

### D.6 Deploy dopiero po zielonych checkach

```yaml
autoDeployTrigger: checksPass
```

Komentarz w konfiguracji poprzedniej aplikacji mówi wprost, po co: przy `commit` deployuje się
**wszystko, co wpadnie na main**, łącznie z czerwonym buildem — i tak raz popsuty obraz trafił
na produkcję.

Do tego CI ma job, który **buduje ten sam Dockerfile i uruchamia kontener**: pyta go o
`/api/v1/health` i sprawdza, czy `/dashboard` zwraca SPA. Dockerfile, który się nie buduje, to
awaria w miejscu, na które nikt nie patrzy; ten job przenosi ją do pull requesta. A ponieważ
deploy wymaga zielonych checków, obraz, który nie wstaje, nie ma jak pojechać.

### D.7 Jeden menedżer pakietów

Frontend przeszedł w całości na **pnpm**. Powód jest ten sam, który stoi za `--frozen-lockfile`
i za przypiętym `serverVersion`: **projekt ma mówić jednym głosem**. Wcześniej repo było npm-owe
(commitowany `package-lock.json`, `npm ci` w CI), a lokalnie potrafiło się na nim wywołać
`pnpm install` — i to właśnie kosztowało dwa zepsute `node_modules` w Stage 4.

Trzy miejsca trzymają teraz tę samą wersję:

```json
"packageManager": "pnpm@10.30.2"
```

CI nie podaje wersji w ogóle — `pnpm/action-setup` czyta ją z tego pola, więc pin istnieje w
jednym miejscu. Dockerfile instaluje pnpm jawnie zamiast przez corepack: pobieranie samego
menedżera pakietów w trakcie builda to jeszcze jedna rzecz, która może paść.

Kolejność kroków w CI jest istotna i łatwo ją pomylić: **`pnpm/action-setup` musi iść przed
`actions/setup-node`**, bo `cache: pnpm` pyta pnpm, gdzie leży jego store. Odwrotna kolejność
kończy się błędem, którego treść nie wskazuje na kolejność.

`--frozen-lockfile` to odpowiednik `npm ci`: instalacja **odmawia**, zamiast po cichu
doinstalować zależność, której lockfile nie opisuje.

Zysk techniczny poza samą spójnością: pnpm nie hoistuje. `node_modules` to dowiązania do
jednego store'u, więc **pakiet nie widzi zależności, których sam nie zadeklarował**. W układzie
npm-owym import przechodzi przypadkiem, dopóki cudza tranzytywna zależność nie zmieni wersji —
i wtedy psuje się bez związku z jakąkolwiek zmianą w kodzie. Warto sprawdzić po migracji, czy
build i testy nadal przechodzą: jeśli któryś pakiet żył z takiego cichego importu, pnpm to
ujawni od razu.

### D.8 Wersja serwera to konfiguracja

Pierwszy przebieg joba `Production image` padł — i dobrze, bo padł w PR-ze, a nie na produkcji.

```
In InvalidPlatformVersion.php line 21:
  Invalid platform version "" specified.
```

Winny był `cache:warmup` w Dockerfile. Rozgrzewanie kontenera w trakcie builda zamienia
pierwsze żądanie po deployu z wolnego w szybkie, ale wymaga zmiennych, bez których aplikacja w
`prod` nie wstanie — więc dostaje atrapy. Atrapa `DATABASE_URL` nie miała `serverVersion`, a
recepta Doctrine zostawia `server_version` **zakomentowane**, z uwagą, żeby ustawić je albo
tam, albo w URL-u. Skoro było w URL-u lokalnym i w CI, brak w trzecim miejscu nie rzucał się w
oczy.

Prostsza łatka to dopisać parametr do atrapy. Gorsza, bo zostawia dokładnie ten sam błąd
czekający na produkcji: connection string wklejony do formularza hostingu **bardzo łatwo
wkleić bez parametru**, a komunikat, który wtedy dostajesz, nie wspomina, czego brakuje —
kontener po prostu nie wstaje.

Wersja poszła więc do `doctrine.yaml`. Doctrine potrzebuje platformy, żeby w ogóle wygenerować
SQL, a platforma jest **własnością aplikacji**, nie poszczególnego połączenia: lokalnie, w CI i
na Neonie to ten sam PostgreSQL 17. `serverVersion` w URL-u nadal nadpisuje ten domyślny, więc
nic nie tracimy.

Przy okazji krok warmupu w buildzie stał się testem tej decyzji: leci z gołym URL-em, więc
gdyby domyślna wersja zniknęła, obraz przestałby się budować.

### D.9 Connection string nie jest przenośny

Neon daje gotowy string do skopiowania. Wklejony do `dbal:run-sql` lokalnie nie zadziałał:

```
SQLSTATE[08006] ERROR: Endpoint ID is not specified. Either please upgrade the postgres
client library (libpq) for SNI support or pass the endpoint ID ... '?options=endpoint%3D<id>'
```

Neon trzyma tysiące baz pod jednym adresem i rozróżnia je po **SNI** — rozszerzeniu TLS, w
którym klient podaje nazwę hosta jeszcze przed zestawieniem szyfrowanego kanału. libpq umie to
od wersji 14; ta w XAMPP-ie jest starsza, więc serwer nie wie, którą bazę otworzyć.

Podpowiedziane w błędzie `?options=endpoint=...` **nie przechodzi przez Doctrine**. DBAL nie
przekazuje query stringa do sterownika, tylko składa DSN z zamkniętej listy kluczy
(`vendor/doctrine/dbal/src/Driver/PDO/PgSQL/Driver.php`): host, port, dbname, `sslmode`,
certyfikaty, `application_name`, `gssencmode`. Wszystko poza tą listą — `options`,
`channel_binding` — jest po cichu ignorowane. Warto to wiedzieć, zanim się doda parametr i
uzna, że działa.

Zadziałało `PGOPTIONS=endpoint=ep-...` w środowisku, bo tę zmienną czyta samo libpq, poniżej
Doctrine. Obejście jest potrzebne wyłącznie przy starym kliencie — obraz produkcyjny stoi na
Debianie z libpq 15+, który SNI wysyła sam.

Dwie rzeczy przy okazji:

**Procenty w `DATABASE_URL`.** Drugie obejście Neona to wpisanie endpointu w pole hasła. Nie
da się: `%3D` w wartości zmiennej środowiskowej Symfony bierze za placeholder parametru
(`env(resolve:...)`) i wywala się na „non-existent parameter". Znak procenta w tej zmiennej
trzeba by podwoić.

**Baza mówi, co naprawdę ma.** `SELECT version()` na Neonie zwrócił PostgreSQL **18.6**, choć
lokalnie i w CI stoi 17. Bez znaczenia — DBAL 4 ma jeden próg na 12 — ale to jest ta klasa
rozbieżności, o której lepiej wiedzieć z zapytania niż z założenia.

### D.10 Pierwszy deploy: dwie usterki

**1. `exec: frankenphp: Operation not permitted`, wyjście 126.**

Kontener zbudował się i wystartował, po czym padł na ostatniej linii entrypointu. Wyjście 126
znaczy „znalezione, ale nie da się uruchomić" — w odróżnieniu od 127, czyli „nie znalezione".
Skoro sam entrypoint się wykonał, odpadał bit wykonywalności i znaki końca linii; problem był
w tym, co entrypoint próbował uruchomić.

Winne okazały się **file capabilities**. Obraz FrankenPHP nadaje binarce:

```
setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp
```

żeby serwer mógł zająć port 80 bez bycia rootem. Uprawnienie siedzi w atrybucie rozszerzonym
pliku, a `+e` znaczy „efektywne". Gdy platforma uruchamia kontener z **obciętym zbiorem
ograniczającym** (capability bounding set), jądro nie ma jak tego uprawnienia przyznać — i
odmawia samego `execve`, zwracając `EPERM`. Nie „program wystartował i nie mógł otworzyć
portu", tylko „program w ogóle się nie uruchomił".

Nasz serwer słucha na `$PORT`, czyli 8080. Uprawnienie do portów uprzywilejowanych jest tu
martwym balastem, więc znika w Dockerfile. Asercja sprawdza **stan końcowy**, a nie samo
usunięcie — liczy się to, że binarka nie niesie uprawnień, nie to, która warstwa je zdjęła.

**Dlaczego CI tego nie złapało** — a przecież job `Production image` uruchamia kontener i pyta
go o zdrowie. Bo runner GitHuba daje kontenerowi **domyślny** zestaw capabilities, w którym
`cap_net_bind_service` jest. `exec` przechodził, health check odpowiadał, wszystko zielone.
Stąd poprawka w CI: `--cap-drop=ALL` przy `docker run`. To ostrzej niż jakakolwiek platforma,
więc ta klasa błędu ląduje teraz w pull requeście.

Morał ogólniejszy: **test, który jest łagodniejszy od produkcji, daje fałszywe poczucie
bezpieczeństwa.** Uruchomienie kontenera to za mało; trzeba go uruchomić w warunkach co
najmniej tak ciasnych jak docelowe.

**2. `render.yaml` w ogóle nie został przeczytany.**

Usługa powstała jako zwykły Web Service z repozytorium, a nie jako **Blueprint**. Render czyta
`render.yaml` wyłącznie w tym drugim trybie. Skutki były widoczne dopiero po zajrzeniu w
ustawienia: zero zmiennych środowiskowych, brak `healthCheckPath`, a Auto-Deploy ustawiony na
`On Commit` zamiast `checksPass` — czyli dokładne przeciwieństwo zamierzonego zachowania, bo
każdy merge na `main` szedłby na produkcję **bez czekania na zielone CI**.

To tłumaczyło też brak migracji: `RUN_RELEASE_ON_START` nie istniało, więc warunek w
entrypoincie był fałszywy i blok się nie wykonał. Potwierdziło to zapytanie wprost do bazy —
zero tabel — zamiast wnioskowania z tego, czego w logu nie było.

Lekcja: **infrastruktura jako kod działa tylko wtedy, gdy platforma ją faktycznie czyta.**
Plik w repozytorium niczego nie gwarantuje; po pierwszej konfiguracji trzeba obejrzeć
ustawienia usługi i sprawdzić, że mówią to samo, co plik.

### Pytania na rozmowę — wdrożenie

**Czemu nie generować pary kluczy JWT przy starcie kontenera?**
Bo instancja może się restartować częściej, niż się wydaje — darmowy plan usypia po kwadransie
ciszy. Nowe klucze przy każdym starcie unieważniają wszystkie wydane tokeny, więc każdy kolejny
odwiedzający wylogowywałby poprzedniego. Klucze to konfiguracja, nie artefakt buildu.

**Co daje jeden obraz z SPA i API zamiast dwóch usług?**
Jeden origin. Refresh token jest ciasteczkiem o wąskiej ścieżce — z jednego hosta działa bez
CORS-a z poświadczeniami, bez preflightów i bez `SameSite=None`, które wymagałoby HTTPS i
osłabiało ochronę przed CSRF.

**Dlaczego `LIKE` przestał działać po zmianie bazy?**
Bo w MySQL nieczułość na wielkość liter dawała kolacja `_ci` kolumny, a nie zapytanie.
PostgreSQL porównuje bajty. Poprawne rozwiązanie to wymusić składanie po obu stronach
(`LOWER(...) LIKE :param`), czyli powiedzieć wprost to, na co wcześniej się liczyło.

**Kontener działa lokalnie i w CI, a na hostingu wychodzi z kodem 126. Od czego zaczynasz?**
126 to „znalezione, ale nieuruchamialne", więc nie szukam literówki w ścieżce — to byłoby 127.
Jeśli sam skrypt startowy się wykonał, zostają prawa i uprawnienia tego, co on uruchamia: bit
wykonywalności, znaki końca linii w shebangu i **file capabilities**. To ostatnie tłumaczy
różnicę między środowiskami, bo lokalnie i na runnerze kontener dostaje domyślny zbiór
capabilities, a platforma hostingowa węższy — a binarki z efektywnym uprawnieniem, którego
jądro nie może przyznać, nie da się w ogóle wykonać.

**Dodajesz parametr do connection stringa i nic się nie zmienia. Dlaczego?**
Bo DBAL nie przekazuje query stringa sterownikowi — składa DSN z zamkniętej listy kluczy, a
resztę ignoruje bez ostrzeżenia. Parametry spoza tej listy trzeba podać kanałem, który
sterownik czyta: zmienną środowiskową libpq albo opcją sterownika, zależnie od parametru.

**Skąd Doctrine wie, jakiej wersji serwera używa, i czemu to ważne?**
Z `server_version` w konfiguracji albo z `serverVersion` w connection stringu. Potrzebuje tego,
żeby wybrać platformę i wygenerować SQL — bez wersji nie wstanie, nawet jeśli nigdy nie zapyta
bazy. Dlatego lepiej trzymać ją w konfiguracji: build rozgrzewa cache bez żadnej bazy pod ręką,
a connection string wklejany do formularza łatwo wkleić bez parametru.

**Co daje pnpm poza szybkością?**
Brak hoistingu. `node_modules` to dowiązania do jednego store'u, więc pakiet nie widzi
zależności, których nie zadeklarował — import, który w układzie npm-owym przechodził
przypadkiem, tutaj nie skompiluje się od razu, zamiast paść przy niepowiązanej zmianie wersji
u kogoś innego. Drugie tyle daje sama spójność: jeden lockfile, wersja przypięta w
`packageManager`, ta sama komenda lokalnie, w CI i w obrazie.

**Jak indeks częściowy znalazł błąd, którego testy nie widziały?**
Doctrine w jednym `flush()` robi INSERT-y przed UPDATE-ami, więc przekazanie opaski kapitana
przechodziło przez stan z dwoma kapitanami. Bez ograniczenia stan końcowy był poprawny i nic
nie protestowało; z ograniczeniem baza odrzuciła zapis w locie. Ograniczenia w schemacie łapią
błędy, których nikt nie szukał.

---

## Django → Symfony

Tabela rośnie z każdym etapem.

| Django / DRF | Symfony | Gdzie w tym repo |
| --- | --- | --- |
| `AUTH_USER_MODEL`, `AbstractBaseUser` | `UserInterface` + provider `entity` | `src/Entity/User.php`, `config/packages/security.yaml` |
| `SessionAuthentication` + CSRF | stateless firewall + JWT | `config/packages/security.yaml` |
| `DEFAULT_PERMISSION_CLASSES` | `access_control` + Voters (Stage 2) | `config/packages/security.yaml` |
| DRF `Serializer` (wejście) | DTO + `#[MapRequestPayload]` + Validator | `src/Dto/Input/` |
| DRF `Serializer` (wyjście) | Serializer + output DTO | `src/Dto/Output/` |
| `custom_exception_handler` | listener na `kernel.exception` | `src/EventSubscriber/ApiExceptionSubscriber.php` |
| `manage.py makemigrations` | `doctrine:migrations:diff` | `migrations/` |
| `factory_boy` | `zenstruck/foundry` | `tests/Factory/` |
| `get_object_or_404` + miksin scopujący queryset | `ValueResolverInterface` budujący scope | `src/Http/ValueResolver/ScopeValueResolver.php` |
| DRF `permission_classes` / `has_object_permission` | `Voter` + `#[IsGranted(subject:)]` | `src/Security/Voter/OrganizationVoter.php` |
| `Model.objects.filter(memberships__user=request.user)` | join membershipu w repozytorium | `src/Repository/OrganizationMembershipRepository.php` |
| `models.TextChoices` | backed enum + `enumType:` | `src/Entity/OrganizationRole.php` |
| `transaction.atomic()` | `EntityManager::wrapInTransaction()` | `src/Domain/Organization/OrganizationManager.php` |
| `slugify()` + `AutoSlugField` | `AsciiSlugger` + własny generator unikalności | `src/Service/SlugGenerator.php` |
| DRF `pagination_class` + `PageNumberPagination` | `Doctrine\ORM\Tools\Pagination\Paginator` + `Listing::respond()` | `src/Repository/Listing.php` |
| DRF `filter_backends` / `ordering_fields` | whitelista nazwa-API → wyrażenie DQL | `src/Controller/Api/*Controller.php` |
| `request.query_params` + `serializers.Serializer` | `#[MapQueryString]` + `ListQuery` | `src/Dto/Input/ListQuery.php` |
| `validators=[...]` / `clean_<field>()` | `Constraint` + `ConstraintValidator` | `src/Validator/SeasonName.php` |
| `Model.clean()` z dostępem do powiązań | serwis domenowy | `src/Domain/Squad/SquadManager.php` |
| `UniqueConstraint(condition=Q(...))` | zwykły `UNIQUE` — NULL-e i tak są różne | `src/Entity/RosterEntry.php` |
| `assertNumQueries()` | profiler + `DoctrineDataCollector::getQueryCount()` | `tests/Api/SquadApiTest.php` |
| `DateField` serializowane jako `Y-m-d` | `#[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]` | `src/Dto/Output/SeasonResource.php` |
| `select_for_update()` | `EntityManager::find(..., LockMode::PESSIMISTIC_WRITE)` | `src/Domain/Fixture/FixtureGenerator.php` |
| serwis w `services.py` bez importu modeli | klasa w `src/Domain/**` bez Doctrine | `src/Domain/Fixture/RoundRobinScheduler.php` |
| `SimpleTestCase` (bez bazy) | `PHPUnit\Framework\TestCase` (bez kernela) | `tests/Domain/RoundRobinSchedulerTest.php` |
| `Count('id', filter=Q(...))` | `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` w DQL | `src/Repository/FixtureRepository.php` |
| `.values(...).annotate(...)` | `select()` + `groupBy()` + `getArrayResult()` | `src/Repository/MatchEventRepository.php` |
| tabela wyliczana w `services/standings.py` | serwis kompozycyjny + czysta klasa reguł | `src/Domain/Standings/StandingsCalculator.php` |
| `BaseCommand` w `management/commands/` | `#[AsCommand]` + `SymfonyStyle` | `src/Command/SeedDemoCommand.php` |
| `random.seed(...)` w seedzie | `Randomizer` na `Mt19937` z ustalonym ziarnem | `src/Command/SeedDemoCommand.php` |
| `transaction.on_commit(...)` | dispatch przez transport Doctrine w otwartej transakcji | `src/Domain/Match/MatchLifecycle.php` |
| zadanie Celery + `@shared_task` | `Message` + `#[AsMessageHandler]` | `src/MessageHandler/MatchFinishedHandler.php` |
| Celery Beat / `CELERY_BEAT_SCHEDULE` | `#[AsSchedule]` + `RecurringMessage::every()` | `src/Schedule/ReminderSchedule.php` |
| `celery worker` | `messenger:consume` z `--time-limit` | `dev.ps1`, `docker-entrypoint.sh` |
| martwe zadania w `django-celery-results` | `failure_transport` + `messenger:failed:show` | `config/packages/messenger.yaml` |
| `get_or_create(dedupe_key=...)` | unikalny indeks + sprawdzenie przed zapisem | `src/Domain/Notification/Notifier.php` |
| Django Channels / WebSocket | Mercure (SSE) wbudowany w FrankenPHP | `docker/mercure.caddyfile` |
| `django-ratelimit` / `AxesBackend` | `login_throttling` na firewallu | `config/packages/security.yaml` |
| `@condition` / `ETag` middleware | listener na `kernel.response` | `src/EventSubscriber/ETagSubscriber.php` |
| `mypy --strict` | PHPStan level 8 | `phpstan.dist.neon` |
| `transaction.atomic()` wokół zdarzenia i wyniku | `EntityManager::wrapInTransaction()` | `src/Domain/Match/MatchEventRecorder.php` |
| `django.utils.timezone.now()` | `Psr\Clock\ClockInterface` (podmienialny na `MockClock`) | `src/Domain/Match/MatchLifecycle.php` |
| `TextChoices` + `ALLOWED_TRANSITIONS` w serwisie | metoda na backed enumie | `src/Entity/MatchStatus.php` |
| `whitenoise` serwujący SPA | Caddy (FrankenPHP) + `SpaController` jako fallback | `src/Controller/SpaController.php` |
| `render.yaml` z `autoDeployTrigger: checksPass` | to samo, bez zmian | `render.yaml` |
| release phase / `preDeployCommand` | `RUN_RELEASE_ON_START` w entrypoincie | `docker-entrypoint.sh` |
