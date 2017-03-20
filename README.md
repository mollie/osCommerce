![Mollie](https://www.mollie.com/files/Mollie-Logo-Style-Small.png)

# Let op #

Deze module wordt niet meer onderhouden, ook kan er geen ondersteuning meer worden geboden indien de module niet juist werkt.

Onze excuses voor het ongemak.

# Installatie #

**Let op:** voor de installatie van deze module is FTP-toegang tot je webserver benodigd. Heb je hier geen ervaring mee? Laat de installatie van deze module dan over aan je websitebouwer of serverbeheerder.

+ Download op de [osCommerce Releases](https://github.com/mollie/OsCommerce/releases)-pagina de nieuwste release.
+ Kopieër de inhoud van de gedownloade map `catalog` naar de bestaande osCommerce-installatie op je server.
+ Ga naar uw osCommerce AdminPanel (Beheerpagina).
+ Ga in het menu naar _Modules_ en selecteer _Payments_.
+ Klik rechtsbovenin op de knop _Install module_. Indien correct geïnstalleerd, verschijnen in dit overzicht onze betaalmethodes onder de naam _Mollie_.
+ Klik op _Install module_ bij elke betaalmethode die u wenst te gebruiken.
+ Vul je _Mollie API key_ in en bewaar de instellingen. Je vindt de API key in Mollie Beheer onder [Websiteprofielen](https://www.mollie.com/beheer/account/profielen/). De instellingen worden automatisch toegepast op
alle Mollie-betaalmethodes.


# Over Mollie #
Via [Mollie](https://www.mollie.com/) is gemakkelijk wereldwijd online betaalmethodes aan te sluiten zonder de gebruikelijke technische en administratieve rompslomp. Mollie geeft op ieder moment toegang tot je
transactieoverzichten en andere statistieken. [Mollie](https://www.mollie.com/) is gestart door developers en verwerkt voor meer dan 20.000 websites de online betalingen.


# Ondersteunde betaalmethodes #

### iDEAL ###
Met [iDEAL](https://www.mollie.com/nl/betaaldiensten/ideal/) kun je vertrouwd, veilig en gemakkelijk uw online aankopen afrekenen. iDEAL is het systeem dat direct is gekoppelt aan je internetbankieren.

### Creditcard ###
[Creditcard](https://www.mollie.com/nl/betaaldiensten/creditcard/) is vrijwel de bekendste methode voor het ontvangen van betalingen met wereldwijde dekking. Doordat we onder andere de bekende merken Mastercard en Visa
ondersteunen, zorgt dit direct voor veel potentiële kopers.

### Mister Cash ###
[Mister Cash](https://www.mollie.com/nl/betaaldiensten/mistercash/) maakt gebruik van een fysieke kaart die gekoppeld is aan tegoed op een Belgische bankrekening. Betalingen via Mister Cash zijn gegarandeerd en lijkt
daarmee sterk op iDEAL in Nederland. Daarom is het uitermate geschikt voor uw webwinkel.

### Overboekingen ###
[Overboekingen](https://www.mollie.com/nl/betaaldiensten/overboeking/) binnen de SEPA zone ontvangen via Mollie. Hiermee kun je betalingen ontvangen van zowel particulieren als zakelijke klanten in meer dan 35 Europese
landen.

### PayPal ###
[PayPal](https://www.mollie.com/nl/betaaldiensten/paypal/) is wereldwijd een zeer populaire betaalmethode. In enkele klikken kun je betalingen ontvangen via een bankoverschrijving, creditcard of het PayPal-saldo.

### Bitcoin ###
[Bitcoin](https://www.mollie.com/nl/betaaldiensten/bitcoin/) is een vorm van elektronisch geld. De bitcoin-euro wisselkoers wordt vastgesteld op het moment van de transactie waardoor het bedrag en de uitbetaling zijn
gegarandeerd.

### paysafecard ###
[paysafecard](https://www.mollie.com/nl/betaaldiensten/paysafecard/) is de populairste prepaidcard voor online betalingen die veel door ouders voor hun kinderen wordt gekocht.


# Veelgestelde vragen #

### Ik kan Mollie niet kiezen bij het afrekenen! ###

Als je de _Live API key_ gebruikt, en iDEAL is nog niet voor je account geactiveerd, kan de module geen betaalmethode vinden om de bestelling mee af te ronden. De module is dan niet zichtbaar. Je kunt de _test API key_
gebruiken totdat iDEAL voor je account actief is.

Het is ook mogelijk dat het bedrag van de bestelling to hoog of te laag is voor de beschikbare betaalmethodes. Het is bijvoorbeeld niet mogelijk om betalingen hoger dan € 50.000 af te rekenen met iDEAL.

Als iDEAL geactiveerd is voor uw account en het bedrag klopt ook, controleer dan of de relevante betaalmethodes ingeschakeld staan bij het websiteprofiel in uw Mollie Beheer.

### Moet ik ook een redirect URL of webhook instellen? ###

Het is niet nodig een redirect URL of webhook in te stellen. Dat doet de module zelf automatisch bij elke order.


# Licentie #
[BSD (Berkeley Software Distribution) License](http://www.opensource.org/licenses/bsd-license.php).
Copyright © 2015, Mollie B.V.


# Ondersteuning #
Contact: [www.mollie.com/nl/about](https://www.mollie.com/nl/about) — info@mollie.com — +31 20-612 88 55

+ [Meer informatie over iDEAL via Mollie](https://www.mollie.com/ideal/)
+ [Meer informatie over Creditcard via Mollie](https://www.mollie.com/creditcard/)
+ [Meer informatie over Mister Cash via Mollie](https://www.mollie.com/mistercash/)
+ [Meer informatie over Overboeking via Mollie](https://www.mollie.com/banktransfer/)
+ [Meer informatie over PayPal via Mollie](https://www.mollie.com/paypal/)
+ [Meer informatie over paysafecard via Mollie](https://www.mollie.com/paysafecard/)

![Powered By Mollie](https://www.mollie.com/images/badge-betaling-medium.png)
