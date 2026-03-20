# dashboard_Politica

## Requisiti
- PHP (consigliato XAMPP)

## Installazione locale
1. Copia i file nella cartella `htdocs` di XAMPP.
2. Avvia Apache dal pannello di controllo XAMPP.
3. Apri il browser all'indirizzo `http://localhost/dashboard_politica/index.html`.

## Funzionamento
- Lo script `api_politica.php` recupera i feed RSS ufficiali di ANSA Politica e Gazzetta Ufficiale, li aggrega in JSON e li memorizza in cache per 10 minuti.
- La pagina `index.html` richiama l'API ogni 10 minuti e visualizza le notizie.
