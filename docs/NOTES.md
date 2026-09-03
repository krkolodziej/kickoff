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
