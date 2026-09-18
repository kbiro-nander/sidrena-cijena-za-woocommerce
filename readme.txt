=== Sidrena cijena za WooCommerce ===
Contributors: kristijanbiro
Tags: woocommerce, sidrena cijena, dodatna cijena, cjenik, omnibus, najniža cijena, hrvatska
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 9.0
WC tested up to: 9.9
Stable tag: 1.1.3
License: MIT
License URI: https://opensource.org/licenses/MIT

Sidrena (dodatna) cijena uz svaku cijenu, najniža cijena u 30 dana prije sniženja i strojno čitljiv cjenik (XML/CSV) prema NN 101/2026 i Zakonu o zaštiti potrošača.

== Description ==

Dodatak provodi obveze hrvatskih webshopova koje stupaju na snagu **1. listopada 2026.** (Odluke Vlade RH, NN 101/2026, na temelju Zakona o iznimnim mjerama kontrole cijena, NN 40/2025) te obvezu isticanja najniže cijene u 30 dana (Zakon o zaštiti potrošača, čl. 19):

* **Sidrena (dodatna) cijena** – uz svaku aktualnu cijenu prikazuje se redovna cijena koja je vrijedila na referentni dan (zadano **10. 9. 2026.**; za FMCG kategorije koje ste već označavali može se zadržati **2. 5. 2025.** po kategoriji). Prikaz je jasan, vidljiv i čitljiv, s datumom, na popisu proizvoda, stranici proizvoda, varijacijama, košarici i blagajni (klasični predlošci) te putem podataka Store API-ja za blokovsku košaricu.
* **Najniža cijena u 30 dana prije sniženja** – dodatak bilježi povijest cijena i za vrijeme svakog sniženja prikazuje najnižu cijenu u 30 dana prije početka sniženja te postotak popusta izračunat iz nje.
* **Strojno čitljiv cjenik (XML i/ili CSV)** – generira se automatski svaki dan prije 08:00 (zadano u 06:00 po vremenskoj zoni trgovine) i pri svakoj promjeni cijene usluge, objavljuje se na javnom URL-u `/cjenik/` (`/cjenik/latest.xml`, `/cjenik/latest.csv`), s propisanim poljima (naziv, šifra, marka, jedinica mjere, cijena za jedinicu mjere, maloprodajna cijena, posebni oblik prodaje, sidrena cijena, barkod, dostupnost), propisanim nazivom datoteke (oblik prodajnog objekta, adresa, oznaka, broj pohrane, datum i vrijeme) i čuvanjem verzija najmanje 30 dana. Datoteke su dostupne bez prijave i bez zaštite od robota.
* **Više prodajnih objekata** – uz webshop možete dodati poslovnice (oblik, adresa, oznaka, broj pohrane). Svaki prodajni objekt dobiva vlastitu datoteku s propisanim nazivom, vlastitu arhivu od 30 dana i vlastite adrese (`/cjenik/{oznaka}/latest.xml`), kako traži t. VI. Odluke – i kada su cijene u svim objektima jednake.
* **Alati** – jednim klikom snimite trenutne redovne cijene kao sidrene cijene (akcijske se cijene nikad ne kopiraju), uvezite sidrene cijene iz CSV-a (šifra → cijena), izvezite ih, te zakažite automatsko snimanje za budući datum (npr. „bazna cijena” od 17. 11. 2026.).
* **Generički mehanizam referentnih cijena** – uz sidrenu cijenu možete uključiti i drugu referentnu cijenu (bazna cijena) čim ministarstvo objavi pravilnik.
* WP-CLI naredbe, Action Scheduler, HPOS kompatibilnost, predlošci koje tema može nadjačati, kuke/filtri s prefiksom `scwc_`.

= Što dodatak NE radi =

* Ne ograničava cijene – sidrena cijena je informacija, ne ograničenje.
* Ne šalje ništa državnim tijelima – cjenik se samo objavljuje na vašoj stranici.
* Ne zna cijene koje su vrijedile prije instalacije – sidrene cijene morate snimiti/uvesti sami (v. Alati), a najniža cijena u 30 dana računa se iz povijesti od trenutka instalacije (do tada se, prema postavci, koristi redovna cijena i označava u administraciji).

= Blokovska košarica i blagajna =

U blokovskoj košarici i blagajni (WooCommerce Blocks) sidrena cijena prikazuje se kao dodatni redak podataka stavke; puni prikaz oznake u blokovima planiran je za sljedeću verziju. Klasična košarica/blagajna (shortcode) imaju potpuni prikaz.

= Za razvojne inženjere =

* Funkcije za teme: `sidrena_cijena( $product, $args )`, `scwc_get_reference_price( $product, 'anchor' )`, `scwc_get_lowest_30_day_price( $product )`, `scwc_discount_percent( $product )`.
* Shortcode: `[sidrena_cijena id="123" key="anchor"]`.
* Predložak: kopirajte `templates/price-badge.php` u `vasa-tema/sidrena-cijena/price-badge.php`.
* Filtri: `scwc_reference_price_types`, `scwc_reference_date`, `scwc_should_render_badge`, `scwc_price_badge_html`, `scwc_is_service`, `scwc_product_brand`, `scwc_barcode_meta_keys`, `scwc_price_list_item`, `scwc_price_list_csv_header`, `scwc_price_list_csv_row`.
* Akcije: `scwc_price_changed`, `scwc_price_list_generated`, `scwc_snapshot_completed`, `scwc_import_completed`.

== Usklađenost s NN 101/2026 ==

**Odluka o objavi cjenika (1213)**

* t. II. – XML/CSV na vlastitoj stranici; cjenik robe generira se svaki dan (zadano 06:00 po vremenskoj zoni trgovine; ako je vrijeme postavljeno nakon 07:30 administracija upozorava); cjenik usluga osvježava se pri svakoj promjeni (zadano i pri promjeni cijene robe), a promjena prije 08:00 objavljuje se odmah, bez odgode; svaka objavljena verzija čuva se i javno je dostupna najmanje 30 dana (zadano 35).
* t. III./IV. – sva propisana polja za proizvode (naziv, šifra, marka, jedinica mjere, cijena za jedinicu mjere, maloprodajna cijena + posebni oblik prodaje s nazivom, sidrena cijena, barkod, dostupno/nedostupno) i usluge (naziv usluge, maloprodajna cijena + posebni oblik prodaje, sidrena cijena).
* t. VI. – naziv datoteke sadrži oblik, adresu i oznaku prodajnog objekta, broj pohrane te datum i vrijeme objave; svaki prodajni objekt (webshop i svaka poslovnica) ima vlastitu datoteku i arhivu jedinstvene strukture; u cjenik ulaze svi objavljeni proizvodi s cijenom, uključujući skrivene (dostupni izravnim linkom) i proizvode drugih dodataka (paketi, pretplate).
* t. VII. – datoteke su dostupne bez prijave i zaštite od robota, s CORS zaglavljima, `no-cache` na `latest.*`, `index.json` za otkrivanje te izravnim adresama datoteka koje ne ovise o postavkama trajnih veza.

**Odluka o isticanju dodatne cijene (1212)**

* t. II. – sidrena (dodatna) cijena, uvijek redovna cijena bez akcija, prikazuje se uz svaku cijenu na popisu proizvoda, stranici proizvoda, varijacijama, u košarici, mini-košarici i na blagajni (klasični predlošci) te kao redak podataka stavke u blokovskoj košarici; za vlastite bannere i oglase na stranici koristite `[sidrena_cijena]`.
* t. IV. – za FMCG kategorije koje ste već označavali od 2. 5. 2025. odaberite kategorije u postavkama (datum 2. 5. 2025. ne može se automatski utvrditi).

Što dodatak ne može napraviti umjesto vas: cijene u fizičkim poslovnicama (koriste se cijene iz WooCommercea), letke/plakate/vanjske oglase, e-mailove i stranice narudžbe nakon kupnje (namjerno bez oznake), te provjeru iznosa koje sami uvezete CSV-om.

== Installation ==

1. Prenesite mapu dodatka u `wp-content/plugins/` (ZIP paket već sadrži `vendor/`).
2. Aktivirajte dodatak. WooCommerce 9.0+ i PHP 8.1+ su obavezni.
3. **WooCommerce → Sidrena cijena → Prodajni objekti**: unesite adresu, oznaku prodajnog objekta i broj pohrane webshopa (ulaze u naziv datoteke cjenika) te dodajte fizičke poslovnice ako ih imate – svaka dobiva vlastitu datoteku i adresu.
4. **Referentne cijene**: provjerite datum (10. 9. 2026.) i po potrebi dodajte FMCG kategorije s datumom 2. 5. 2025.
5. **WooCommerce → Sidrena cijena – Alati**: snimite trenutne redovne cijene kao sidrene cijene (ili uvezite CSV s cijenama koje su vrijedile 10. 9. 2026.).
6. **Cjenik**: kliknite „Generiraj sada” i provjerite `https://vasa-trgovina.hr/cjenik/`.
7. Preporuka: postavite pravi sistemski cron (`DISABLE_WP_CRON` + `wp cron event run --due-now` ili `wp action-scheduler run`) kako bi generiranje u 06:00 bilo pouzdano. Alternativa: vanjski cron koji poziva `https://vasa-trgovina.hr/cjenik/?scwc_run=1&key=VAŠ_KLJUČ` (ključ je u postavkama).
8. Ako koristite cache dodatak ili CDN: URL-ovi `/cjenik/` i `/cjenik/latest.*` ne smiju se keširati (dodatak šalje `no-cache` zaglavlja i `DONOTCACHEPAGE`).

== Frequently Asked Questions ==

= Otvorio sam /cijene i dobio 404 =

Javna adresa cjenika je `https://vasa-trgovina.hr/cjenik/` (te `/cjenik/latest.xml` i `/cjenik/latest.csv`), a ne `/cijene`. Slug mijenjate u WooCommerce → Sidrena cijena → Cjenik. Ako i `/cjenik/` vraća 404: otvorite Postavke → Trajne veze i kliknite „Spremi promjene” (bez izmjena) ili u kartici Cjenik kliknite „Ponovno zakaži zadatke”; s „običnim” trajnim vezama koristite `?scwc_cjenik=latest&scwc_format=xml`. Kartica Cjenik prikazuje rezultat automatske provjere dostupnosti.

= Što je sidrena cijena? =

Službeno „dodatna maloprodajna cijena” – redovna cijena (bez posebnih oblika prodaje) koja je za proizvod/uslugu vrijedila na dan 10. 9. 2026. Mora se istaknuti uz svaku aktualnu cijenu i svako oglašavanje cijene.

= Proizvod je uveden nakon 10. 9. 2026. =

Označite „Nema referentne cijene” na proizvodu (alat za snimanje to radi automatski za proizvode kreirane nakon referentnog datuma). Prikazuje se samo aktualna cijena.

= Moram li čuvati stare cjenike? =

Da, najmanje 30 dana. Dodatak čuva i javno poslužuje verzije prema postavci zadržavanja (min. 30 dana) i ne briše ih pri deaktivaciji.

= Kako se računa postotak popusta? =

Iz najniže cijene u 30 dana prije početka sniženja (a ne iz redovne cijene), zaokruženo na niže.

== Changelog ==

= 1.1.3 =
* Licenca promijenjena u MIT; projekt objavljen na GitHubu s README-om i CI provjerama.
* Polja za datum i vrijeme u postavkama dovoljno su široka da prikažu cijelu vrijednost.

= 1.1.2 =
* Korisnički priručnik (PDF) uz dodatak. Kartica Cjenik jasno prikazuje javnu adresu (/cjenik/) i automatski provjerava dostupnost; gumb „Ponovno zakaži zadatke” obnavlja i rute; rute se samoobnavljaju ako ih drugi dodatak obriše; obavijest s javnom adresom do prvog generiranja.

= 1.1.1 =
* Provjera usklađenosti s NN 101/2026: upozorenje kad je generiranje zakazano nakon 07:30; promjene prije 08:00 objavljuju se odmah; skriveni proizvodi i proizvodi drugih vrsta (paketi, pretplate) ulaze u cjenik; mini-košarica i osvježavanje pri svakoj promjeni cijene uključeni zadano; trajni ključevi prodajnih objekata (preimenovanje ne gubi arhivu); promjene otkrivene noćnom provjerom također osvježavaju cjenik; izravne adrese datoteka u index.json.

= 1.1.0 =
* Više prodajnih objekata: svaka poslovnica ima vlastitu datoteku cjenika, arhivu i URL (`/cjenik/{oznaka}/latest.xml`); `/cjenik/latest.xml` i dalje poslužuje webshop.
* index.json navodi sve prodajne objekte.

= 1.0.0 =
* Prva verzija: sidrena cijena, povijest cijena i najniža cijena u 30 dana, XML/CSV cjenik s javnim URL-om i zadržavanjem, alati za snimanje/uvoz/izvoz, WP-CLI, Store API.
