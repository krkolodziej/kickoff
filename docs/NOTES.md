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
Gdyby `api` był wyżej, żądanie logowania trafiłoby do authenticatora JWT, nie znalazłoby
nagłówka i skończyłoby się 401 — zanim ktokolwiek spojrzałby na hasło.

**`stateless: true`** znaczy: żadnej sesji, żadnego ciasteczka `PHPSESSID`, żadnego
`_security_main` w sesji. Konsekwencja, o którą pyta się na rozmowach: **skoro nie ma
sesyjnego ciasteczka, które przeglądarka dołącza automatycznie, to nie ma też czego
podpiąć pod atak CSRF** — dlatego przy `json_login` na stateless firewallu nie konfiguruje
się tokenu CSRF. To nie zaniedbanie, tylko wniosek z braku ciasteczka sesyjnego.

Uwaga do `check_path: api_auth_login`. Trasa **musi istnieć**, mimo że kontroler nigdy się
nie wykona — routing (`RouterListener`, priorytet 32) działa **przed** firewallem
(`FirewallListener`, priorytet 8), więc bez trasy dostalibyśmy 404 zanim authenticator
zdążyłby zajrzeć do ciała żądania. Stąd metoda, która tylko rzuca wyjątek:
`backend/src/Controller/Api/AuthController.php:41`.

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
   `propertyPath === 'passwordConfirm'`, a cała reszta API mówi snake_case. Gdyby to poszło
   na wyjściu bez konwersji, frontend szukałby `fields.password_confirm`, nie znalazłby nic
   i **po cichu nie pokazał żadnego błędu** — formularz odmawiałby wysłania bez wyjaśnienia.
   Konwersja: `backend/src/Http/ViolationFormatter.php`.
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
