# Sidrena cijena za WooCommerce

> **Napomena: ovaj projekt je „vibe coded”** – razvijen uz pomoć umjetne inteligencije (Claude) prema tekstu propisa, pokriven automatiziranim testovima i isproban na stvarnom webshopu, ali **nije ga pregledao pravnik**. Koristite ga **isključivo na vlastitu odgovornost**: prije 1. 10. 2026. provjerite ga na vlastitoj trgovini, usporedite izlaz s propisima i po potrebi se posavjetujte s pravnim savjetnikom. Autor ne odgovara za bilo kakvu štetu, kaznu ili trošak (v. [Licenca i odricanje od odgovornosti](#licenca-i-odricanje-od-odgovornosti)).

WordPress/WooCommerce dodatak koji hrvatskim webshopovima omogućuje usklađenost s obvezama koje stupaju na snagu **1. listopada 2026.**:

- **sidrena (dodatna) cijena** uz svaku cijenu – redovna cijena koja je vrijedila 10. 9. 2026. (za FMCG kategorije opcionalno 2. 5. 2025.),
- **najniža cijena u 30 dana prije sniženja** i postotak popusta izračunat iz nje (Zakon o zaštiti potrošača, čl. 19),
- **strojno čitljiv cjenik (XML/CSV)** na javnoj adresi `/cjenik/`, s propisanim poljima i nazivom datoteke, generiran svaki dan prije 08:00 i pri svakoj promjeni cijene, s arhivom od najmanje 30 dana i **zasebnom datotekom za svaki prodajni objekt** (webshop i poslovnice).

Pravna osnova: [Odluka o isticanju dodatne cijene](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html) i [Odluka o objavi cjenika proizvoda i usluga](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html) (NN 101/2026) na temelju Zakona o iznimnim mjerama kontrole cijena (NN 40/2025).

## Značajke

- Oznaka sidrene cijene na popisu proizvoda, stranici proizvoda, varijacijama, košarici, mini-košarici i blagajni; podaci za blokovsku košaricu putem Store API-ja; shortcode `[sidrena_cijena]` za bannere.
- Povijest cijena i najniža cijena u 30 dana; referenca ostaje zamrznuta tijekom postupnih sniženja.
- XML i CSV cjenik s poljima naziv, šifra, marka, jedinica mjere, cijena za jedinicu mjere, maloprodajna cijena + posebni oblik prodaje, sidrena cijena, barkod, dostupnost; usluge s vlastitim skupom polja.
- Jedna datoteka po prodajnom objektu, naziv `oblik_adresa_oznaka_brojpohrane_YYYYMMDD_HHMMSS.xml`, javne adrese `/cjenik/`, `/cjenik/latest.xml`, `/cjenik/{oznaka}/latest.xml`, `/cjenik/index.json`.
- Alati: snimanje trenutnih redovnih cijena kao sidrenih (akcijske se nikad ne kopiraju), CSV uvoz/izvoz, automatsko snimanje na budući datum (npr. „bazna cijena” od 17. 11. 2026.).
- Action Scheduler ili WP-Cron, vanjski cron URL, WP-CLI naredbe (`wp scwc …`), HPOS kompatibilnost, predlošci koje tema može nadjačati, kuke s prefiksom `scwc_`.

## Zahtjevi

WordPress 6.4+, WooCommerce 9.0+, PHP 8.1+.

## Instalacija

**Iz ZIP-a (preporučeno):** preuzmite `sidrena-cijena-za-woocommerce-x.y.z.zip` s [Releases](../../releases) stranice i prenesite ga u WordPress → Dodaci → Dodaj novi → Prenesi dodatak. ZIP već sadrži `vendor/` i priručnik `prirucnik.pdf`.

**Iz izvornog koda:**

```bash
git clone https://github.com/kbiro-nander/sidrena-cijena-za-woocommerce.git
cd sidrena-cijena-za-woocommerce
composer install --no-dev
# ili izgradite ZIP za prijenos:
composer install && bin/build-zip.sh   # → dist/sidrena-cijena-za-woocommerce-x.y.z.zip
```

## Ažuriranje

Dodatak provjerava [GitHub Releases](../../releases) i WordPress prikazuje uobičajeni gumb „Ažuriraj sada” kad postoji novija verzija. Ručno ažuriranje: prenesite novi ZIP i odaberite „Zamijeni trenutni prenesenim”.

## Brzi start

1. Aktivirajte dodatak.
2. **WooCommerce → Sidrena cijena → Prodajni objekti**: unesite adresu, oznaku i broj pohrane webshopa; dodajte poslovnice ako ih imate.
3. **Referentne cijene**: provjerite datum 10. 9. 2026.; po potrebi dodajte FMCG kategorije s datumom 2. 5. 2025.
4. **WooCommerce → Sidrena cijena – Alati**: snimite trenutne redovne cijene kao sidrene cijene ili uvezite CSV s cijenama od 10. 9. 2026.
5. **Cjenik**: kliknite „Generiraj sada” i otvorite `https://vasa-trgovina.hr/cjenik/` (adresa je `/cjenik/`, ne `/cijene`).
6. Postavite pravi sistemski cron (v. priručnik, poglavlje 7).

## Dokumentacija

- **Priručnik za vlasnike trgovine (PDF):** [`docs/manual/Sidrena-cijena-za-WooCommerce-prirucnik.pdf`](docs/manual/Sidrena-cijena-za-WooCommerce-prirucnik.pdf) – postavke polje po polje, alati, javni cjenik, automatika, kontrolna lista usklađenosti, rješavanje problema.
- **`readme.txt`** – WordPress.org opis, tablica usklađenosti s NN 101/2026, česta pitanja.
- **Specifikacija dizajna:** `docs/superpowers/specs/2026-09-18-sidrena-cijena-design.md`.

## Razvoj

```bash
composer install
composer test            # PHPUnit (Brain Monkey, bez WordPress instalacije)
composer phpstan         # PHPStan razina 6 sa WordPress/WooCommerce stubovima
vendor/bin/phpcs         # WordPress Coding Standards
bin/make-pot.sh          # languages/*.pot
bin/build-manual.sh      # docs/manual/*.pdf (WeasyPrint)
bin/build-zip.sh         # dist/*.zip
```

Struktura: `src/` (PSR-4, `SidrenaCijena\`), `templates/` (nadjačivi predlošci), `assets/`, `languages/`, `tests/` (jedinični testovi), `docs/`, `bin/`. Svi testovi rade bez WordPress instalacije; prije objave preporučujemo provjeru na testnoj (staging) stranici.

## Doprinosi

Prijave grešaka i pull requestovi su dobrodošli. Prije slanja pokrenite `composer test`, `composer phpstan` i `vendor/bin/phpcs`. Za tumačenje propisa obratite se pravnom savjetniku – rasprave o pravnom tumačenju otvorite kao issue s poveznicom na izvor.

## Licenca i odricanje od odgovornosti

Objavljeno pod [MIT licencom](LICENSE): dodatak smijete slobodno koristiti, mijenjati i dalje distribuirati, uključujući u komercijalne svrhe, uz zadržavanje obavijesti o autorskim pravima.

Softver se isporučuje **„kakav jest”, bez ikakvog jamstva**. Autor ne odgovara za bilo kakvu štetu, kaznu ili trošak proizašao iz korištenja dodatka, uključujući odluke inspekcijskih tijela. Dodatak i priručnik **nisu pravni savjet**; za usklađenost vaše trgovine odgovorni ste sami i preporučujemo provjeru s pravnim savjetnikom.

Autor: Kristijan Biro.
