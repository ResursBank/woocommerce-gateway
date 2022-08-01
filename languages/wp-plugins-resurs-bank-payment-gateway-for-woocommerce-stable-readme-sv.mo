��    ;      �  O   �        K   	  �   U  �   �    �  j   �  �   	  �   �  _   	  m   ~	  �   �	  e   u
  �   �
  �   �  �   7  �   �  �   �  t   J     �  <   �  1    5   4     j  -   �  
   �  �   �  �   �  �   _     �  n   �  d   c  �   �  j   �  �     V   �       $   :  J   _  �  �  '   @  +   h  ,   �  �   �     R  �  g  L  �  �   6  N   �  �          	     B   &  �   i  �   2  �         !  �   :!  #   �!  �   ""  �  �"  L   h$  �   �$  �   g%  =  �%  j   9'  |   �'  �   !(  `   �(  m   )  �   �)  e   *  �   *  �   4+  �   �+  �   �,  �   H-  �   .     �.  4   �.  >  �.  ;   �/     :0  +   R0     ~0  �   �0  �   _1  �   =2     �2  |   �2  v   _3  �   �3  y   �4  �   I5  U   �5     E6  )   d6  a   �6  �  �6  ,   �8  /   �8  ,   9  �   .9     �9  �  �9  c  s;  �   �<  R   ]=     �=     �>  
   �>  A   �>  �   ?    �?    �@  !   B  �   5B  "   
C  �   -C     3                    
         4   !       9             0   )   .   $          (                 -          5          	            /   &      %   7                  1                 '       8          "   6         +         #           2         ,   ;           :       *          (Or install and activate the plugin through the WordPress plugin installer) 401 = Unauthorized.
<strong>Cause</strong>: Bad credentials
<strong>Solution</strong>: Contact Resurs support for support questions regarding API credentials. 403 = Forbidden.
<strong>Cause</strong>: This may be more common during test.
<strong>Solution:</strong> Resolution: Contact Resurs for support. <a href="https://curl.haxx.se">curl</a> or <a href="https://php.net/manual/en/book.stream.php">PHP stream</a> features active (for REST based actions).
To not loose important features, we recommend you to have this extension enabled - if you currently run explicitly with streams. <a href="https://resursbankplugins.atlassian.net/browse/WOO-636">WOO-636</a> WP &#043; Woo tested up to... <a href="https://resursbankplugins.atlassian.net/browse/WOO-637">WOO-637</a> Purchase-Overlay are shown by mistake in some themes <a href="https://resursbankplugins.atlassian.net/browse/WOO-659">WOO-659</a> Removing order lines from orders created by other methods than Resurs <a href="https://resursbankplugins.atlassian.net/browse/WOO-662">WOO-662</a> Statusqueue issues <a href="https://resursbankplugins.atlassian.net/browse/WOO-665">WOO-665</a> kredit blir fel status completed <a href="https://resursbankplugins.atlassian.net/browse/WOO-667">WOO-667</a> finalizepayment no longer fires errors due to a fix in ecom <a href="https://resursbankplugins.atlassian.net/browse/WOO-676">WOO-676</a> Ändra DENIED-meddelande <a href="https://test.resurs.com/docs/display/ecom/Hosted+Payment+Flow">Hosted Payment Flow</a>. A paypal like checkout where most of the payment events takes place at Resurs. <a href="https://test.resurs.com/docs/display/ecom/Resurs+Checkout+Web">Resurs Checkout Web</a>. Iframe integration. Currently supporting <strong>RCOv1 and RCOv2</strong>. <a href="https://test.resurs.com/docs/display/ecom/Simplified+Flow+API">Simplified Shop Flow</a>. Integrated checkout that works with WooCommerce built in features. <a href="https://test.resurs.com/docs/display/ecom/WooCommerce">Project URL</a> - <a href="https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/">Plugin URL</a> <strong>Included</strong> <a href="https://test.resurs.com/docs/x/TYNM">EComPHP</a> (Bundled vendor) <a href="https://bitbucket.org/resursbankplugins/resurs-ecomphp.git">Bitbucket</a> <strong>Required</strong>: <a href="https://php.net/manual/en/class.soapclient.php">SoapClient</a> with xml drivers. About Activate the plugin through the "Plugins" menu in WordPress. As of v2.2.12, we do support SWISH and similar "instant debitable" payment methods, where payments tend to be finalized/debited long before shipping has been made. You can read more about it <a href="https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12">here</a>. Can I upgrade WooCommerce with your plugin installed? Compatibility and requirements Configure the plugin via admin control panel. Contribute Do you think there are ways to make our plugin even better? Join our project for woocommerce at <a href="https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce">Bitbucket</a> For a full list of changes, <a href="https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md">look here</a> - CHANGELOG.md is also included in this package. HTTPS <strong>must</strong> be <strong>fully</strong> enabled. This is a callback security measure, which is required from Resurs. Handling decimals Help us translate the plugin by joining <a href="https://crwd.in/resurs-bank-woocommerce-gateway">Crowdin</a>! Help us translate the plugin by joining <a href="https://crwd.in/resursbankwoocommerce">Crowdin</a>! If unsure about upgrades, take a look at resursbankgateway.php under "WC Tested up to". That section usually
changes (after internal tests has been made) to match the base requirements, so you can upgrade without upgrade
warnings. If you have a FTP-client or similar, make sure to give this path write-access for at least your webserver. Included: <a href="https://netcurl.org/docs/">NetCURL</a> <a href="https://www.netcurl.org">Bitbucket</a>. NetCURL handles all of the communication
drivers just mentioned. Make sure the plugin has write access to itself under the includes folder (see below). Multisite/WordPress Networks Official payment gateway for Resurs. On upgrades, make sure you synchronize language if WordPress suggest this. PHP: <a href="https://docs.woocommerce.com/document/server-requirements/">Take a look here</a> to keep up with support. As of aug
2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
Also, <a href="https://wordpress.org/news/2019/04/minimum-php-version-update/">read here</a> for information about lower versions
of PHP. Syntax for this release is written for releases lower than 7.0 Plugin is causing 40X errors on my site Resurs Bank Payment Gateway for WooCommerce Resurs Bank Payment Gateway for WooCommerce. Setting decimals to 0 in WooCommerce will result in an incorrect rounding of product prices. It is therefore adviced to set decimal points to 2. Supported shop flows The most commonly used path to the plugin folder's include-path is set to:
/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes
This path has to be write-accessible for your web server or the plugin won't work properly since the payment methods are written to disk as classes. If you have login access to your server by SSH you could simply run this kind of command: The plugin <strong>do</strong> support WordPress networks (aka multisite), however it does not support running one webservice account over many sites at once. The main rule that Resurs works with is that one webservice account only works for one site. Running multiple sites do <strong>require</strong> multiple webservice accounts! There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message there are few things to take in consideration: There's an order created but there is no order information connected to Resurs This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs support. Upgrade notice Upgrading Upload the plugin archive to the "/wp-content/plugins/" directory. Want to add a new language to this plugin? You can contribute via <a href="https://translate.wordpress.org/projects/wp-plugins/resurs-bank-payment-gateway-for-woocommerce">translate.wordpress.org</a>. When developing the plugin for Woocommerce, we usually follow the versions for WooCommerce and always upgrading when
there are new versions out. That said, it is <strong>normally</strong> also safe to upgrade to the latest woocommerce. When upgrading the plugin via WordPress plugin manager, make sure that you payment methods are still there. If you are unsure, just visit the configuration panel for Resurs once after upgrading since, the plugin are rewriting files if they are missing. WooCommerce: v3.5.0 or higher! WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
recommended to go for the latest version as soon as possible if you're not already there. Write access to the includes folder You may want to look further at <a href="https://test.resurs.com/docs/display/ecom/WooCommerce">https://test.resurs.com/docs/display/ecom/WooCommerce</a> for updates regarding this plugin. Project-Id-Version: Plugins - Resurs Bank Payment Gateway for WooCommerce - Stable Readme (latest release)
PO-Revision-Date: 2022-08-01 08:08+0200
Last-Translator: Tomas Tornevall <thorne@tornevall.net>
Language-Team: 
Language: sv_SE
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n != 1;
X-Generator: Poedit 3.1.1
 (Eller installera och aktivera tillägget via WordPress plugin-installation) 401 = Obehörig.
<strong>Orsak</strong>: Felaktiga autentiseringsuppgifter
<strong>Lösning</strong>: Kontakta Resurs support för supportfrågor om API-autentiseringsuppgifter. 403 = Förbjudet.
<strong>Orsak</strong>: Detta kan vara vanligare under testet.
<strong>Lösning:</strong> Lösning: Kontakta Resurs för support. <a href="https://curl.haxx.se">curl</a> - eller <a href="https://php.net/manual/en/book.stream.php">PHP-streams-funktioner</a> aktiva (för REST-baserade åtgärder).
För att inte förlora viktiga funktioner rekommenderar vi att du har aktiverat det här tillägget - om du för närvarande enbart använder streams. <a href="https://resursbankplugins.atlassian.net/browse/WOO-636">WOO-636</a> WP + Woo testade upp till ... <a href="https://resursbankplugins.atlassian.net/browse/WOO-637">WOO-637</a> Purchase-Overlay visas av misstag i vissa teman <a href="https://resursbankplugins.atlassian.net/browse/WOO-659">WOO-659</a> Ta bort orderrader från order som skapats med andra metoder än Resurs <a href="https://resursbankplugins.atlassian.net/browse/WOO-662">WOO-662</a> Statusqueue-problem <a href="https://resursbankplugins.atlassian.net/browse/WOO-665">WOO-665</a> kredit blir fel status completed <a href="https://resursbankplugins.atlassian.net/browse/WOO-667">WOO-667</a> slutförabetalning avfyrar inte längre fel på grund av en fix i ecom <a href="https://resursbankplugins.atlassian.net/browse/WOO-676">WOO-676</a> Ändra DENIED-meddelande <a href="https://test.resurs.com/docs/display/ecom/Hosted+Payment+Flow">Hostat betalningsflöde</a>. En PayPal-liknande kassa där de flesta betalningshändelserna sker hos Resurs. <a href="https://test.resurs.com/docs/display/ecom/Resurs+Checkout+Web">Resurs Checkout-webb</a>. Iframe-integration. Stöder för närvarande <strong>RCOv1 och RCOv2</strong>. <a href="https://test.resurs.com/docs/display/ecom/Simplified+Flow+API">Förenklat butiksflöde</a>. Integrerad kassa som fungerar med WooCommerce inbyggda funktioner. <a href="https://test.resurs.com/docs/display/ecom/WooCommerce">Projekt-URL</a> - <a href="https://wordpress.org/plugins/resurs-bank-payment-gateway-for-woocommerce/">URL för plugin</a> <strong>Inkluderat</strong> <a href="https://test.resurs.com/docs/x/TYNM">EComPHP</a> (Paketerad vendor) <a href="https://bitbucket.org/resursbankplugins/resurs-ecomphp.git">Bitbucket</a> <strong>Krävs</strong>: <a href="https://php.net/manual/en/class.soapclient.php">SoapClient</a> med xml-drivrutiner och tillägg. Om Aktivera tillägget via menyn "Plugins" i WordPress. Från och med v2.2.12 stöder vi SWISH och liknande "omedelbara debiterbara" betalningsmetoder, där betalningar tenderar att slutföras / debiteras långt innan frakt har gjorts. Du kan läsa mer om det <a href="https://test.resurs.com/docs/display/ecom/CHANGELOG+-+WooCommerce#CHANGELOG-WooCommerce-2.2.12">här</a>. Kan jag uppgradera WooCommerce med erat plugin installerat? Kompatibilitet och krav Konfigurera tillägget via kontrollpanelen. Bidra Tror du att det finns sätt att göra vårt plugin ännu bättre? Gå med i vårt projekt för woocommerce på <a href="https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce">Bitbucket</a> För en fullständig lista över ändringar, <a href="https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/CHANGELOG.md">titta här</a> - CHANGELOG.md ingår också i detta paket. HTTPS <strong>måste</strong> vara <strong>helt</strong> aktiverat. Detta är en säkerhetsåtgärd för callbacks som krävs av Resurs Bank. Hantering av decimaler Hjälp oss att översätta plugin genom att gå med <a href="https://crwd.in/resurs-bank-woocommerce-gateway">i Crowdin</a>! Hjälp oss att översätta tillägget genom att gå med <a href="https://crwd.in/resursbankwoocommerce">i Crowdin</a>! Om du är osäker på uppgraderingar, ta en titt på resursbankgateway.php under "WC Tested up to". Det avsnittet brukar
ändras (efter att interna tester har gjorts) för att matcha baskraven, så att du kan uppgradera utan uppgraderingsvarningar. Om du har en FTP-klient eller liknande, se till att ge den här sökvägen skrivåtkomst för åtminstone din webbserver. Ingår: <a href="https://netcurl.org/docs/">NetCURL</a> <a href="https://www.netcurl.org">Bitbucket</a>. NetCURL hanterar all kommunikation
förare som just nämnts. Se till att plugin har skrivåtkomst till sig själv under includes-mappen (se nedan) Multisite / WordPress-nätverk Officiell betalningslösning för Resurs. Försäkra dig om att du synkroniserar språket om WordPress föreslår detta vid uppgraderingar. PHP: <a href="https://docs.woocommerce.com/document/server-requirements/">Ta en titta här</a> för att hänga med i supporten. Från och med augusti
2021, är WooCommerce på väg att gå upp i version 7.4 eller högre.
Se även <a href="https://wordpress.org/news/2019/04/minimum-php-version-update/">här</a> för att få information om lägre versioner
av PHP. Syntaxen för denna releasen, är skriven för lägre releaser än 7.0 Tillägget orsakar 40X-fel på min webbplats Resurs Bank betalningslösning för WooCommerce Resurs Bank Payment Gateway for WooCommerce. Att ställa in decimaler till 0 i WooCommerce resulterar i en felaktig avrundning av produktpriserna. Det rekommenderas därför att sätta decimaler till 2. Butiksflöden som stöds Den vanligast använda platsen för pluginmappen include-path brukar vara:
/wp-content/plugin/resurs-bank-payment-gateway-for-woocommerce/includes
Denna plats måste webservern ha skrivbehörighet i, annars kommer tillägget inte att fungera ordentligt eftersom betalmetoder skrivs till disk som klasser. Om du har login-behörighet till din behörighet via SSH kan du enkelt köra detta kommando: Pluginet <strong>har</strong> stöd för WordPress-nätverk (även känt som multisite), men det saknas stöd för att köra ett webbtjänstkonto över flera webbplatser samtidigt. Huvudregeln som Resurs arbetar med är att ett webbtjänstkonto bara fungerar för en webbplats. Att köra flera webbplatser <strong>kräver</strong> flera webbtjänstkonton! Det finns flera orsaker till 40X-felen, men om de kastas från ett EComPHP API-meddelande finns det några saker
att ta hänsyn till: Det finns en skapad order men det finns ingen orderinformation kopplad till Resurs Detta är en vanlig fråga om kundåtgärder och hur ordern har skapats/signerats. De flesta uppgifterna placeras vanligtvis i orderanteckningarna för beställningen, men om du behöver mer information kan du också överväga att kontakta Resurs support. Uppgraderingsnotering Uppgradera Ladda upp tilläggspaketet till katalogen "/wp-content/plugins/". Vill du lägga till ett nytt språk i det här tillägget? Du kan bidra via <a href="https://translate.wordpress.org/projects/wp-plugins/resurs-bank-payment-gateway-for-woocommerce">translate.wordpress.org</a>. När vi utvecklar plugin för Woocommerce följer vi vanligtvis versionerna för WooCommerce och uppgraderar alltid när
det finns nya versioner ute. Med det sagt, så är det <strong>normalt</strong> sett också säkert att uppgradera till den senaste woocommerce-versionen. När du uppgraderar tillägget via WordPress plugin manager, se till att dina betalningsmetoder fortfarande finns kvar. Om du är osäker är det bara att besöka konfigurationspanelen för Resurs en gång efter uppgraderingen eftersom detta kommer skriva om de filer som saknas. WooCommerce: v3.5.0 eller högre! WordPress: Helst minst v5.5. Det stödjer äldre och kommer förmodligen fortsätta göra det, men det är starkt
rekommenderat att gå till den senaste versionen så snart som möjligt om du inte redan är där. Skrivåtkomst till mappen includes Du kanske vill titta närmare på <a href="https://test.resurs.com/docs/display/ecom/WooCommerce">https://test.resurs.com/docs/display/ecom/WooCommerce</a> för uppdateringar om detta plugin. 