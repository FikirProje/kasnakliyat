<?php
declare(strict_types=1);

ob_start(static function (string $html): string {
    $lang = function_exists('lc_public_locale') ? lc_public_locale() : 'en';
    $ui = [
        'tr'=>['Product'=>'Pazar','Pricing'=>'Fiyatlandırma','Themes'=>'Temalar','Contact'=>'İletişim','Buy now'=>'Satın al','Dashboard'=>'Panel','Licences'=>'Lisanslar','Orders'=>'Siparişler','Requests'=>'Talepler','Support'=>'Destek','Health'=>'Sistem durumu','Settings'=>'Ayarlar','Sign out'=>'Çıkış','Transportation'=>'Yazılım ve temalar'],
        'en'=>['Product'=>'Marketplace','Pricing'=>'Pricing','Themes'=>'Themes','Contact'=>'Contact','Buy now'=>'Buy now','Dashboard'=>'Dashboard','Licences'=>'Licences','Orders'=>'Orders','Requests'=>'Requests','Support'=>'Support','Health'=>'Health','Settings'=>'Settings','Sign out'=>'Sign out','Transportation'=>'Software and themes'],
        'de'=>['Product'=>'Marktplatz','Pricing'=>'Preise','Themes'=>'Themes','Contact'=>'Kontakt','Buy now'=>'Kaufen','Dashboard'=>'Dashboard','Licences'=>'Lizenzen','Orders'=>'Bestellungen','Requests'=>'Anfragen','Support'=>'Support','Health'=>'Systemstatus','Settings'=>'Einstellungen','Sign out'=>'Abmelden','Transportation'=>'Software und Themes'],
        'fr'=>['Product'=>'Marketplace','Pricing'=>'Tarifs','Themes'=>'Thèmes','Contact'=>'Contact','Buy now'=>'Acheter','Dashboard'=>'Tableau de bord','Licences'=>'Licences','Orders'=>'Commandes','Requests'=>'Demandes','Support'=>'Support','Health'=>'État du système','Settings'=>'Paramètres','Sign out'=>'Déconnexion','Transportation'=>'Logiciels et thèmes'],
        'es'=>['Product'=>'Marketplace','Pricing'=>'Precios','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Dashboard'=>'Panel','Licences'=>'Licencias','Orders'=>'Pedidos','Requests'=>'Solicitudes','Support'=>'Soporte','Health'=>'Estado del sistema','Settings'=>'Ajustes','Sign out'=>'Cerrar sesión','Transportation'=>'Software y temas'],
        'it'=>['Product'=>'Marketplace','Pricing'=>'Prezzi','Themes'=>'Temi','Contact'=>'Contatti','Buy now'=>'Acquista','Dashboard'=>'Dashboard','Licences'=>'Licenze','Orders'=>'Ordini','Requests'=>'Richieste','Support'=>'Supporto','Health'=>'Stato del sistema','Settings'=>'Impostazioni','Sign out'=>'Esci','Transportation'=>'Software e temi'],
        'pt'=>['Product'=>'Marketplace','Pricing'=>'Preços','Themes'=>'Temas','Contact'=>'Contacto','Buy now'=>'Comprar','Dashboard'=>'Painel','Licences'=>'Licenças','Orders'=>'Pedidos','Requests'=>'Pedidos','Support'=>'Suporte','Health'=>'Estado do sistema','Settings'=>'Definições','Sign out'=>'Sair','Transportation'=>'Software e temas'],
        'nl'=>['Product'=>'Marktplaats','Pricing'=>'Prijzen','Themes'=>'Thema’s','Contact'=>'Contact','Buy now'=>'Kopen','Dashboard'=>'Dashboard','Licences'=>'Licenties','Orders'=>'Bestellingen','Requests'=>'Aanvragen','Support'=>'Support','Health'=>'Systeemstatus','Settings'=>'Instellingen','Sign out'=>'Afmelden','Transportation'=>'Software en thema’s'],
        'ru'=>['Product'=>'Маркетплейс','Pricing'=>'Цены','Themes'=>'Темы','Contact'=>'Контакты','Buy now'=>'Купить','Dashboard'=>'Панель','Licences'=>'Лицензии','Orders'=>'Заказы','Requests'=>'Запросы','Support'=>'Поддержка','Health'=>'Состояние системы','Settings'=>'Настройки','Sign out'=>'Выйти','Transportation'=>'ПО и темы'],
        'ar'=>['Product'=>'السوق','Pricing'=>'الأسعار','Themes'=>'القوالب','Contact'=>'اتصل بنا','Buy now'=>'اشترِ الآن','Dashboard'=>'لوحة التحكم','Licences'=>'التراخيص','Orders'=>'الطلبات','Requests'=>'الطلبات','Support'=>'الدعم','Health'=>'حالة النظام','Settings'=>'الإعدادات','Sign out'=>'تسجيل الخروج','Transportation'=>'البرامج والقوالب'],
        'fa'=>['Product'=>'بازار','Pricing'=>'قیمت‌ها','Themes'=>'قالب‌ها','Contact'=>'تماس','Buy now'=>'خرید','Dashboard'=>'داشبورد','Licences'=>'مجوزها','Orders'=>'سفارش‌ها','Requests'=>'درخواست‌ها','Support'=>'پشتیبانی','Health'=>'وضعیت سیستم','Settings'=>'تنظیمات','Sign out'=>'خروج','Transportation'=>'نرم‌افزار و قالب‌ها'],
        'zh'=>['Product'=>'市场','Pricing'=>'价格','Themes'=>'主题','Contact'=>'联系','Buy now'=>'立即购买','Dashboard'=>'控制台','Licences'=>'许可证','Orders'=>'订单','Requests'=>'请求','Support'=>'支持','Health'=>'系统状态','Settings'=>'设置','Sign out'=>'退出','Transportation'=>'软件与主题'],
        'ja'=>['Product'=>'マーケット','Pricing'=>'料金','Themes'=>'テーマ','Contact'=>'お問い合わせ','Buy now'=>'購入','Dashboard'=>'ダッシュボード','Licences'=>'ライセンス','Orders'=>'注文','Requests'=>'リクエスト','Support'=>'サポート','Health'=>'システム状態','Settings'=>'設定','Sign out'=>'ログアウト','Transportation'=>'ソフトウェアとテーマ'],
        'ko'=>['Product'=>'마켓플레이스','Pricing'=>'가격','Themes'=>'테마','Contact'=>'문의','Buy now'=>'구매하기','Dashboard'=>'대시보드','Licences'=>'라이선스','Orders'=>'주문','Requests'=>'요청','Support'=>'지원','Health'=>'시스템 상태','Settings'=>'설정','Sign out'=>'로그아웃','Transportation'=>'소프트웨어와 테마'],
    ][$lang] ?? [];
    $searchLabel = ['tr'=>'Ara','en'=>'Search','de'=>'Suchen','fr'=>'Rechercher','es'=>'Buscar','it'=>'Cerca','pt'=>'Pesquisar','nl'=>'Zoeken','ru'=>'Поиск','ar'=>'بحث','fa'=>'جستجو','zh'=>'搜索','ja'=>'検索','ko'=>'검색'][$lang] ?? 'Search';
    $html = str_replace('<a href="./shop">Product</a>', '<a href="./shop">'.htmlspecialchars($ui['Product'] ?? 'Marketplace', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</a><a href="./search">'.htmlspecialchars($searchLabel, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</a>', $html);
    $html = str_replace(['>Transportation</span>','TofiDesk Transportation'], ['>'.htmlspecialchars($ui['Transportation'] ?? 'Software and themes', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</span>','TofiDesk '.htmlspecialchars($ui['Transportation'] ?? 'Software and themes', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')], $html);
    foreach ($ui as $source => $target) {
        $html = str_replace('>'.$source.'</a>', '>'.htmlspecialchars($target, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</a>', $html);
        $html = str_replace('>'.$source.'<', '>'.htmlspecialchars($target, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'<', $html);
    }
    $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($requestPath === '/product' && function_exists('one')) {
        parse_str((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: ''), $query);
        $slug = trim((string)($query['slug'] ?? ''));
        if ($slug !== '') {
            $seo = one('SELECT t.seo_title,t.seo_description,t.keywords,t.canonical_url,t.og_image FROM products p JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status=? WHERE p.slug=? AND p.status=? LIMIT 1', [$lang, 'published', $slug, 'published']);
            if ($seo) {
                $description = (string)($seo['seo_description'] ?? '');
                if ($description !== '') $html = preg_replace('/<meta name="description" content="[^"]*">/', '<meta name="description" content="'.htmlspecialchars($description, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'">', $html, 1) ?? $html;
                if (!empty($seo['keywords'])) $html = str_replace('<meta name="description"', '<meta name="keywords" content="'.htmlspecialchars((string)$seo['keywords'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'"/><meta name="description"', $html);
                $og = '';
                if (!empty($seo['og_image']) && filter_var($seo['og_image'], FILTER_VALIDATE_URL)) $og .= '<meta property="og:image" content="'.htmlspecialchars((string)$seo['og_image'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'"/><meta name="twitter:image" content="'.htmlspecialchars((string)$seo['og_image'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'"/>';
                if ($og !== '') $html = str_replace('<meta property="og:title"', $og.'<meta property="og:title"', $html);
                if (!empty($seo['canonical_url']) && filter_var($seo['canonical_url'], FILTER_VALIDATE_URL)) {
                    $canonical = htmlspecialchars((string)$seo['canonical_url'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                    $html = preg_replace('/<link rel="canonical" href="[^"]*">/', '<link rel="canonical" href="'.$canonical.'">', $html, 1) ?? $html;
                    $html = preg_replace('/<meta property="og:url" content="[^"]*"\/>/', '<meta property="og:url" content="'.$canonical.'"/>', $html, 1) ?? $html;
                }
            }
        }
    }
    return $html;
});

function catalog_label(string $lang, string $key): string {
    $planLabels = [
        'tr'=>['plans'=>'Lisans seçenekleri','choose_plan'=>'Bu planı seç'],
        'en'=>['plans'=>'Licence options','choose_plan'=>'Choose this plan'],
        'de'=>['plans'=>'Lizenzoptionen','choose_plan'=>'Diesen Plan wählen'],
        'fr'=>['plans'=>'Options de licence','choose_plan'=>'Choisir ce plan'],
        'es'=>['plans'=>'Opciones de licencia','choose_plan'=>'Elegir este plan'],
        'it'=>['plans'=>'Opzioni di licenza','choose_plan'=>'Scegli questo piano'],
        'pt'=>['plans'=>'Opções de licença','choose_plan'=>'Escolher este plano'],
        'ru'=>['plans'=>'Варианты лицензии','choose_plan'=>'Выбрать этот план'],
        'ar'=>['plans'=>'خيارات الترخيص','choose_plan'=>'اختيار هذه الخطة'],
        'fa'=>['plans'=>'گزینه‌های مجوز','choose_plan'=>'انتخاب این طرح'],
        'nl'=>['plans'=>'Licentieopties','choose_plan'=>'Dit plan kiezen'],
        'zh'=>['plans'=>'许可证选项','choose_plan'=>'选择此方案'],
        'ja'=>['plans'=>'ライセンスオプション','choose_plan'=>'このプランを選択'],
        'ko'=>['plans'=>'라이선스 옵션','choose_plan'=>'이 플랜 선택'],
    ];
    if (isset($planLabels[$lang][$key])) return $planLabels[$lang][$key];
    $labels = [
        'tr'=>['compare'=>'Karşılaştır','remove'=>'Kaldır','save'=>'Favorilere kaydet','signin_save'=>'Kaydetmek için giriş yap','signin'=>'Müşteri girişi','back'=>'Pazara dön','about'=>'Bu ürün hakkında','assets'=>'Demo, dosyalar ve dokümantasyon','reviews'=>'Müşteri değerlendirmeleri','no_reviews'=>'Henüz değerlendirme yok.','related'=>'Benzer ürünler','write_review'=>'Değerlendirme yaz','rating'=>'Puan','title'=>'Başlık','review'=>'Değerlendirme','submit_review'=>'İncelemeye gönder','view'=>'Ürünü incele →','search'=>'Ara','all'=>'Tümü','software'=>'Yazılım','themes'=>'Temalar','addons'=>'Eklentiler','newest'=>'En yeni','popular'=>'Popüler','search_title'=>'Yazılım, tema ve eklenti bulun.','search_lead'=>'Ürünleri sektör, özellik ve kategoriye göre keşfedin.','no_match'=>'Eşleşen ürün yok','try_again'=>'Başka bir arama yapın veya son ürünlere göz atın.','comparison'=>'Ürün karşılaştırma','comparison_title'=>'Ürünleri yan yana karşılaştırın.','comparison_lead'=>'Karşılaştırma listenizde dört ürüne kadar tutabilirsiniz.','empty_compare'=>'Henüz ürün seçilmedi. Pazardan ürün ekleyin.','product_missing'=>'Ürün bulunamadı','not_available'=>'Bu ürün şu anda kullanılamıyor.','preparing'=>'Ürün içeriği hazırlanıyor','translation_missing'=>'Seçilen dil için yayınlanmış içerik henüz yok.','buy'=>'Satın al / lisans iste →'],
        'en'=>['compare'=>'Compare','remove'=>'Remove','save'=>'Save to favourites','signin_save'=>'Sign in to save','signin'=>'Customer sign in','back'=>'Back to marketplace','about'=>'About this product','assets'=>'Demo, files and documentation','reviews'=>'Customer reviews','no_reviews'=>'No reviews yet.','related'=>'Related products','write_review'=>'Write a review','rating'=>'Rating','title'=>'Title','review'=>'Review','submit_review'=>'Submit for review','view'=>'View product →','search'=>'Search','all'=>'All','software'=>'Software','themes'=>'Themes','addons'=>'Add-ons','newest'=>'Newest','popular'=>'Popular','search_title'=>'Find software, themes and add-ons.','search_lead'=>'Discover products by industry, feature and category.','no_match'=>'No matching products','try_again'=>'Try another search or browse the latest releases.','comparison'=>'Product comparison','comparison_title'=>'Compare products side by side.','comparison_lead'=>'Keep up to four published products in your comparison list.','empty_compare'=>'No products selected. Add products from the marketplace.','product_missing'=>'Product not found','not_available'=>'This product is not available.','preparing'=>'Product content is being prepared','translation_missing'=>'This product does not have published content for the selected language yet.','buy'=>'Buy / request licence →'],
    ];
    $labels['de']=['compare'=>'Vergleichen','remove'=>'Entfernen','save'=>'Zu Favoriten','signin_save'=>'Zum Speichern anmelden','signin'=>'Kundenanmeldung','back'=>'Zurück zum Marktplatz','about'=>'Über dieses Produkt','assets'=>'Demo, Dateien und Dokumentation','reviews'=>'Kundenbewertungen','no_reviews'=>'Noch keine Bewertungen.','related'=>'Ähnliche Produkte','write_review'=>'Bewertung schreiben','rating'=>'Bewertung','title'=>'Titel','review'=>'Rezension','submit_review'=>'Zur Prüfung senden','view'=>'Produkt ansehen →','search'=>'Suchen','all'=>'Alle','software'=>'Software','themes'=>'Themes','addons'=>'Add-ons','newest'=>'Neueste','popular'=>'Beliebt','search_title'=>'Software, Themes und Add-ons finden.','search_lead'=>'Produkte nach Branche, Funktion und Kategorie entdecken.','no_match'=>'Keine passenden Produkte','try_again'=>'Andere Suche oder aktuelle Veröffentlichungen ansehen.','comparison'=>'Produktvergleich','comparison_title'=>'Produkte nebeneinander vergleichen.','comparison_lead'=>'Bis zu vier veröffentlichte Produkte vergleichen.','empty_compare'=>'Keine Produkte ausgewählt. Fügen Sie Produkte im Marktplatz hinzu.','product_missing'=>'Produkt nicht gefunden','not_available'=>'Dieses Produkt ist nicht verfügbar.','preparing'=>'Produktinhalt wird vorbereitet','translation_missing'=>'Für die gewählte Sprache gibt es noch keinen veröffentlichten Inhalt.','buy'=>'Kaufen / Lizenz anfordern →'];
    $labels['fr']=['compare'=>'Comparer','remove'=>'Supprimer','save'=>'Ajouter aux favoris','signin_save'=>'Connectez-vous pour enregistrer','signin'=>'Connexion client','back'=>'Retour au marketplace','about'=>'À propos de ce produit','assets'=>'Démo, fichiers et documentation','reviews'=>'Avis clients','no_reviews'=>'Aucun avis pour le moment.','related'=>'Produits associés','write_review'=>'Écrire un avis','rating'=>'Note','title'=>'Titre','review'=>'Avis','submit_review'=>'Envoyer pour validation','view'=>'Voir le produit →','search'=>'Rechercher','all'=>'Tous','software'=>'Logiciels','themes'=>'Thèmes','addons'=>'Extensions','newest'=>'Nouveautés','popular'=>'Populaires','search_title'=>'Trouver des logiciels, thèmes et extensions.','search_lead'=>'Découvrez les produits par secteur, fonction et catégorie.','no_match'=>'Aucun produit correspondant','try_again'=>'Essayez une autre recherche ou consultez les nouveautés.','comparison'=>'Comparaison de produits','comparison_title'=>'Comparez les produits côte à côte.','comparison_lead'=>'Comparez jusqu’à quatre produits publiés.','empty_compare'=>'Aucun produit sélectionné. Ajoutez-en depuis le marketplace.','product_missing'=>'Produit introuvable','not_available'=>'Ce produit n’est pas disponible.','preparing'=>'Le contenu du produit est en préparation','translation_missing'=>'Aucun contenu publié dans la langue sélectionnée.','buy'=>'Acheter / demander une licence →'];
    $common = [
        'es'=>['compare'=>'Comparar','remove'=>'Quitar','save'=>'Guardar en favoritos','signin_save'=>'Inicia sesión para guardar','signin'=>'Acceso de cliente','back'=>'Volver al marketplace','about'=>'Sobre este producto','assets'=>'Demo, archivos y documentación','reviews'=>'Opiniones de clientes','no_reviews'=>'Aún no hay opiniones.','related'=>'Productos relacionados','write_review'=>'Escribir una opinión','rating'=>'Valoración','title'=>'Título','review'=>'Opinión','submit_review'=>'Enviar para revisión','view'=>'Ver producto →','search'=>'Buscar','all'=>'Todos','software'=>'Software','themes'=>'Temas','addons'=>'Complementos','newest'=>'Más recientes','popular'=>'Populares','search_title'=>'Encuentra software, temas y complementos.','search_lead'=>'Descubre productos por sector, función y categoría.','no_match'=>'No hay productos coincidentes','try_again'=>'Prueba otra búsqueda o consulta las novedades.','comparison'=>'Comparación de productos','comparison_title'=>'Compara productos lado a lado.','comparison_lead'=>'Compara hasta cuatro productos publicados.','empty_compare'=>'No hay productos seleccionados. Añade productos del marketplace.','product_missing'=>'Producto no encontrado','not_available'=>'Este producto no está disponible.','preparing'=>'Preparando el contenido del producto','translation_missing'=>'Todavía no hay contenido publicado en el idioma seleccionado.','buy'=>'Comprar / solicitar licencia →'],
        'it'=>['compare'=>'Confronta','remove'=>'Rimuovi','save'=>'Salva nei preferiti','signin_save'=>'Accedi per salvare','signin'=>'Accesso cliente','back'=>'Torna al marketplace','about'=>'Informazioni sul prodotto','assets'=>'Demo, file e documentazione','reviews'=>'Recensioni clienti','no_reviews'=>'Nessuna recensione.','related'=>'Prodotti correlati','write_review'=>'Scrivi una recensione','rating'=>'Valutazione','title'=>'Titolo','review'=>'Recensione','submit_review'=>'Invia per revisione','view'=>'Vedi prodotto →','search'=>'Cerca','all'=>'Tutti','software'=>'Software','themes'=>'Temi','addons'=>'Componenti aggiuntivi','newest'=>'Più recenti','popular'=>'Popolari','search_title'=>'Trova software, temi e componenti aggiuntivi.','search_lead'=>'Scopri i prodotti per settore, funzione e categoria.','no_match'=>'Nessun prodotto corrispondente','try_again'=>'Prova un’altra ricerca o guarda le novità.','comparison'=>'Confronto prodotti','comparison_title'=>'Confronta i prodotti affiancati.','comparison_lead'=>'Confronta fino a quattro prodotti pubblicati.','empty_compare'=>'Nessun prodotto selezionato. Aggiungi prodotti dal marketplace.','product_missing'=>'Prodotto non trovato','not_available'=>'Questo prodotto non è disponibile.','preparing'=>'Contenuto del prodotto in preparazione','translation_missing'=>'Non esiste ancora contenuto pubblicato nella lingua selezionata.','buy'=>'Acquista / richiedi licenza →'],
        'pt'=>['compare'=>'Comparar','remove'=>'Remover','save'=>'Guardar nos favoritos','signin_save'=>'Inicie sessão para guardar','signin'=>'Entrada do cliente','back'=>'Voltar ao marketplace','about'=>'Sobre este produto','assets'=>'Demo, ficheiros e documentação','reviews'=>'Avaliações dos clientes','no_reviews'=>'Ainda não há avaliações.','related'=>'Produtos relacionados','write_review'=>'Escrever avaliação','rating'=>'Classificação','title'=>'Título','review'=>'Avaliação','submit_review'=>'Enviar para revisão','view'=>'Ver produto →','search'=>'Pesquisar','all'=>'Todos','software'=>'Software','themes'=>'Temas','addons'=>'Extensões','newest'=>'Mais recentes','popular'=>'Populares','search_title'=>'Encontre software, temas e extensões.','search_lead'=>'Descubra produtos por setor, função e categoria.','no_match'=>'Nenhum produto correspondente','try_again'=>'Tente outra pesquisa ou veja os lançamentos.','comparison'=>'Comparação de produtos','comparison_title'=>'Compare produtos lado a lado.','comparison_lead'=>'Compare até quatro produtos publicados.','empty_compare'=>'Nenhum produto selecionado. Adicione produtos do marketplace.','product_missing'=>'Produto não encontrado','not_available'=>'Este produto não está disponível.','preparing'=>'Conteúdo do produto em preparação','translation_missing'=>'Ainda não existe conteúdo publicado no idioma selecionado.','buy'=>'Comprar / pedir licença →'],
        'nl'=>['compare'=>'Vergelijken','remove'=>'Verwijderen','save'=>'Opslaan bij favorieten','signin_save'=>'Log in om op te slaan','signin'=>'Klantlogin','back'=>'Terug naar marktplaats','about'=>'Over dit product','assets'=>'Demo, bestanden en documentatie','reviews'=>'Klantbeoordelingen','no_reviews'=>'Nog geen beoordelingen.','related'=>'Gerelateerde producten','write_review'=>'Een beoordeling schrijven','rating'=>'Beoordeling','title'=>'Titel','review'=>'Review','submit_review'=>'Ter beoordeling verzenden','view'=>'Product bekijken →','search'=>'Zoeken','all'=>'Alle','software'=>'Software','themes'=>'Thema’s','addons'=>'Add-ons','newest'=>'Nieuwste','popular'=>'Populair','search_title'=>'Vind software, thema’s en add-ons.','search_lead'=>'Ontdek producten per sector, functie en categorie.','no_match'=>'Geen overeenkomende producten','try_again'=>'Probeer een andere zoekopdracht of bekijk de nieuwste releases.','comparison'=>'Productvergelijking','comparison_title'=>'Vergelijk producten naast elkaar.','comparison_lead'=>'Vergelijk maximaal vier gepubliceerde producten.','empty_compare'=>'Geen producten geselecteerd. Voeg producten toe vanuit de marktplaats.','product_missing'=>'Product niet gevonden','not_available'=>'Dit product is niet beschikbaar.','preparing'=>'Productinhoud wordt voorbereid','translation_missing'=>'Voor de geselecteerde taal is nog geen gepubliceerde inhoud.','buy'=>'Kopen / licentie aanvragen →'],
        'ru'=>['compare'=>'Сравнить','remove'=>'Удалить','save'=>'В избранное','signin_save'=>'Войдите, чтобы сохранить','signin'=>'Вход клиента','back'=>'Вернуться на маркетплейс','about'=>'О продукте','assets'=>'Демо, файлы и документация','reviews'=>'Отзывы клиентов','no_reviews'=>'Отзывов пока нет.','related'=>'Похожие продукты','write_review'=>'Оставить отзыв','rating'=>'Оценка','title'=>'Заголовок','review'=>'Отзыв','submit_review'=>'Отправить на проверку','view'=>'Открыть продукт →','search'=>'Поиск','all'=>'Все','software'=>'ПО','themes'=>'Темы','addons'=>'Дополнения','newest'=>'Новые','popular'=>'Популярные','search_title'=>'Найдите ПО, темы и дополнения.','search_lead'=>'Находите продукты по отрасли, функции и категории.','no_match'=>'Подходящих продуктов нет','try_again'=>'Попробуйте другой запрос или посмотрите новинки.','comparison'=>'Сравнение продуктов','comparison_title'=>'Сравнивайте продукты рядом.','comparison_lead'=>'Сравните до четырёх опубликованных продуктов.','empty_compare'=>'Продукты не выбраны. Добавьте их из маркетплейса.','product_missing'=>'Продукт не найден','not_available'=>'Этот продукт недоступен.','preparing'=>'Содержание продукта готовится','translation_missing'=>'Для выбранного языка пока нет опубликованного содержания.','buy'=>'Купить / запросить лицензию →'],
        'ar'=>['compare'=>'مقارنة','remove'=>'إزالة','save'=>'حفظ في المفضلة','signin_save'=>'سجّل الدخول للحفظ','signin'=>'دخول العميل','back'=>'العودة إلى السوق','about'=>'عن هذا المنتج','assets'=>'عرض تجريبي وملفات ووثائق','reviews'=>'تقييمات العملاء','no_reviews'=>'لا توجد تقييمات بعد.','related'=>'منتجات ذات صلة','write_review'=>'اكتب تقييماً','rating'=>'التقييم','title'=>'العنوان','review'=>'التقييم','submit_review'=>'إرسال للمراجعة','view'=>'عرض المنتج ←','search'=>'بحث','all'=>'الكل','software'=>'برمجيات','themes'=>'قوالب','addons'=>'إضافات','newest'=>'الأحدث','popular'=>'الأكثر شعبية','search_title'=>'ابحث عن البرامج والقوالب والإضافات.','search_lead'=>'اكتشف المنتجات حسب القطاع والميزة والفئة.','no_match'=>'لا توجد منتجات مطابقة','try_again'=>'جرّب بحثاً آخر أو تصفح الإصدارات الجديدة.','comparison'=>'مقارنة المنتجات','comparison_title'=>'قارن المنتجات جنباً إلى جنب.','comparison_lead'=>'يمكنك مقارنة ما يصل إلى أربعة منتجات منشورة.','empty_compare'=>'لم تحدد منتجات بعد. أضف منتجات من السوق.','product_missing'=>'المنتج غير موجود','not_available'=>'هذا المنتج غير متاح.','preparing'=>'يتم إعداد محتوى المنتج','translation_missing'=>'لا يوجد محتوى منشور باللغة المحددة بعد.','buy'=>'شراء / طلب ترخيص ←'],
        'fa'=>['compare'=>'مقایسه','remove'=>'حذف','save'=>'ذخیره در علاقه‌مندی‌ها','signin_save'=>'برای ذخیره وارد شوید','signin'=>'ورود مشتری','back'=>'بازگشت به بازار','about'=>'درباره این محصول','assets'=>'دمو، فایل‌ها و مستندات','reviews'=>'نظرات مشتریان','no_reviews'=>'هنوز نظری ثبت نشده است.','related'=>'محصولات مرتبط','write_review'=>'نوشتن نظر','rating'=>'امتیاز','title'=>'عنوان','review'=>'نظر','submit_review'=>'ارسال برای بررسی','view'=>'مشاهده محصول ←','search'=>'جستجو','all'=>'همه','software'=>'نرم‌افزار','themes'=>'قالب‌ها','addons'=>'افزونه‌ها','newest'=>'جدیدترین','popular'=>'محبوب‌ترین','search_title'=>'نرم‌افزار، قالب و افزونه پیدا کنید.','search_lead'=>'محصولات را بر اساس صنعت، ویژگی و دسته‌بندی پیدا کنید.','no_match'=>'محصولی مطابق یافت نشد','try_again'=>'جستجوی دیگری انجام دهید یا آخرین محصولات را ببینید.','comparison'=>'مقایسه محصولات','comparison_title'=>'محصولات را کنار هم مقایسه کنید.','comparison_lead'=>'تا چهار محصول منتشرشده را مقایسه کنید.','empty_compare'=>'هنوز محصولی انتخاب نشده است. از بازار محصول اضافه کنید.','product_missing'=>'محصول پیدا نشد','not_available'=>'این محصول در دسترس نیست.','preparing'=>'محتوای محصول در حال آماده‌سازی است','translation_missing'=>'برای زبان انتخاب‌شده هنوز محتوای منتشرشده‌ای وجود ندارد.','buy'=>'خرید / درخواست مجوز ←'],
        'zh'=>['compare'=>'比较','remove'=>'移除','save'=>'收藏','signin_save'=>'登录后收藏','signin'=>'客户登录','back'=>'返回市场','about'=>'关于此产品','assets'=>'演示、文件和文档','reviews'=>'客户评价','no_reviews'=>'暂无评价。','related'=>'相关产品','write_review'=>'撰写评价','rating'=>'评分','title'=>'标题','review'=>'评价','submit_review'=>'提交审核','view'=>'查看产品 →','search'=>'搜索','all'=>'全部','software'=>'软件','themes'=>'主题','addons'=>'插件','newest'=>'最新','popular'=>'热门','search_title'=>'查找软件、主题和插件。','search_lead'=>'按行业、功能和类别发现产品。','no_match'=>'没有匹配的产品','try_again'=>'尝试其他搜索或查看最新发布。','comparison'=>'产品比较','comparison_title'=>'并排比较产品。','comparison_lead'=>'最多比较四个已发布产品。','empty_compare'=>'尚未选择产品。请从市场添加产品。','product_missing'=>'未找到产品','not_available'=>'该产品不可用。','preparing'=>'产品内容准备中','translation_missing'=>'所选语言尚无已发布内容。','buy'=>'购买 / 申请许可证 →'],
        'ja'=>['compare'=>'比較','remove'=>'削除','save'=>'お気に入りに保存','signin_save'=>'保存するにはログイン','signin'=>'顧客ログイン','back'=>'マーケットに戻る','about'=>'製品について','assets'=>'デモ、ファイル、ドキュメント','reviews'=>'顧客レビュー','no_reviews'=>'レビューはまだありません。','related'=>'関連製品','write_review'=>'レビューを書く','rating'=>'評価','title'=>'タイトル','review'=>'レビュー','submit_review'=>'審査に送信','view'=>'製品を見る →','search'=>'検索','all'=>'すべて','software'=>'ソフトウェア','themes'=>'テーマ','addons'=>'アドオン','newest'=>'最新','popular'=>'人気','search_title'=>'ソフトウェア、テーマ、アドオンを探す。','search_lead'=>'業界、機能、カテゴリから製品を探せます。','no_match'=>'一致する製品がありません','try_again'=>'別の検索を試すか新着を確認してください。','comparison'=>'製品比較','comparison_title'=>'製品を並べて比較。','comparison_lead'=>'公開済み製品を最大4つ比較できます。','empty_compare'=>'製品が選択されていません。マーケットから追加してください。','product_missing'=>'製品が見つかりません','not_available'=>'この製品は利用できません。','preparing'=>'製品コンテンツを準備中','translation_missing'=>'選択した言語の公開コンテンツはまだありません。','buy'=>'購入 / ライセンスを申請 →'],
        'ko'=>['compare'=>'비교','remove'=>'삭제','save'=>'즐겨찾기에 저장','signin_save'=>'저장하려면 로그인','signin'=>'고객 로그인','back'=>'마켓플레이스로 돌아가기','about'=>'제품 정보','assets'=>'데모, 파일 및 문서','reviews'=>'고객 리뷰','no_reviews'=>'아직 리뷰가 없습니다.','related'=>'관련 제품','write_review'=>'리뷰 작성','rating'=>'평점','title'=>'제목','review'=>'리뷰','submit_review'=>'검토 제출','view'=>'제품 보기 →','search'=>'검색','all'=>'전체','software'=>'소프트웨어','themes'=>'테마','addons'=>'애드온','newest'=>'최신','popular'=>'인기','search_title'=>'소프트웨어, 테마와 애드온 찾기.','search_lead'=>'산업, 기능과 카테고리별로 제품을 찾아보세요.','no_match'=>'일치하는 제품이 없습니다','try_again'=>'다른 검색을 시도하거나 최신 릴리스를 확인하세요.','comparison'=>'제품 비교','comparison_title'=>'제품을 나란히 비교하세요.','comparison_lead'=>'게시된 제품을 최대 4개까지 비교할 수 있습니다.','empty_compare'=>'선택한 제품이 없습니다. 마켓플레이스에서 추가하세요.','product_missing'=>'제품을 찾을 수 없습니다','not_available'=>'이 제품은 사용할 수 없습니다.','preparing'=>'제품 콘텐츠 준비 중','translation_missing'=>'선택한 언어로 게시된 콘텐츠가 아직 없습니다.','buy'=>'구매 / 라이선스 요청 →'],
    ];
    foreach ($common as $locale => $copy) $labels[$locale] = $copy;
    $fallback = $labels['en'];
    return $labels[$lang][$key] ?? $fallback[$key] ?? $key;
}

/** Resolve a storefront display currency without changing the customer's checkout choice. */
function catalog_display_currency(string $lang): string
{
    $explicit = strtoupper(trim((string)($_GET['currency'] ?? '')));
    if (in_array($explicit, ['TRY','USD','EUR','GBP','CAD','AUD','AED','SAR','RUB'], true)) return $explicit;
    $country = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '')));
    $countryCurrencies = [
        'TR'=>'TRY','GB'=>'GBP','US'=>'USD','CA'=>'CAD','AU'=>'AUD',
        'DE'=>'EUR','AT'=>'EUR','CH'=>'EUR','FR'=>'EUR','BE'=>'EUR','ES'=>'EUR',
        'IT'=>'EUR','PT'=>'EUR','NL'=>'EUR','IE'=>'EUR','FI'=>'EUR','SE'=>'EUR',
        'NO'=>'EUR','DK'=>'EUR','PL'=>'EUR','CZ'=>'EUR','HU'=>'EUR','RO'=>'EUR',
        'RU'=>'RUB','AE'=>'AED','SA'=>'SAR','CN'=>'USD','TW'=>'USD','JP'=>'USD',
        'KR'=>'USD','IR'=>'USD',
    ];
    if (isset($countryCurrencies[$country])) return $countryCurrencies[$country];
    return [
        'tr'=>'TRY','en'=>'USD','de'=>'EUR','fr'=>'EUR','es'=>'EUR','it'=>'EUR','pt'=>'EUR',
        'nl'=>'EUR','ru'=>'RUB','ar'=>'AED','fa'=>'USD','zh'=>'USD','ja'=>'USD','ko'=>'USD',
    ][$lang] ?? 'EUR';
}

/** Convert a stored minor-unit amount for storefront display only. Checkout
 * still re-resolves the price server-side and never trusts this display value. */
function catalog_display_amount(int $minor, string $baseCurrency, string $displayCurrency): int
{
    $baseCurrency = strtoupper($baseCurrency);
    $displayCurrency = strtoupper($displayCurrency);
    // The storefront uses the ISO code TRY, while PayTR and the configured
    // rate fallback use TL. Keep the public label modern and normalize only
    // the internal conversion key.
    $baseCurrency = $baseCurrency === 'TRY' ? 'TL' : $baseCurrency;
    $displayCurrency = $displayCurrency === 'TRY' ? 'TL' : $displayCurrency;
    if ($minor <= 0 || $baseCurrency === $displayCurrency) return $minor;
    $rate = function_exists('checkout_currency_rate') ? checkout_currency_rate($baseCurrency, $displayCurrency) : 1.0;
    return max(1, (int)round($minor * max(0.000001, $rate)));
}

function catalog_currency_key(string $currency): string
{
    $currency = strtoupper(trim($currency));
    return $currency === 'TRY' ? 'TL' : $currency;
}

/** Render a compact storefront currency selector while preserving language and filters. */
function catalog_currency_switcher(string $lang): string
{
    $currency = catalog_display_currency($lang);
    $labels = [
        'tr'=>'Para birimi', 'en'=>'Currency', 'de'=>'Währung', 'fr'=>'Devise',
        'es'=>'Moneda', 'it'=>'Valuta', 'pt'=>'Moeda', 'nl'=>'Valuta', 'ru'=>'Валюта',
        'ar'=>'العملة', 'fa'=>'ارز', 'zh'=>'货币', 'ja'=>'通貨', 'ko'=>'통화',
    ];
    $options = [
        'TRY'=>'TRY · ₺', 'USD'=>'USD · $', 'EUR'=>'EUR · €', 'GBP'=>'GBP · £',
        'CAD'=>'CAD · $', 'AUD'=>'AUD · $', 'AED'=>'AED · د.إ', 'SAR'=>'SAR · ﷼', 'RUB'=>'RUB · ₽',
    ];
    $query = $_GET;
    unset($query['currency']);
    $query['lang'] = $lang;
    $html = '<form method="get" class="currency-switcher" aria-label="'.esc($labels[$lang] ?? 'Currency').'">';
    foreach ($query as $key => $value) {
        if (is_array($value)) continue;
        $html .= '<input type="hidden" name="'.esc((string)$key).'" value="'.esc((string)$value).'">';
    }
    $html .= '<label><span>'.esc($labels[$lang] ?? 'Currency').'</span><select name="currency" onchange="this.form.submit()">';
    foreach ($options as $code => $label) $html .= '<option value="'.esc($code).'"'.($currency === $code ? ' selected' : '').'>'.esc($label).'</option>';
    return $html.'</select></label></form>';
}

function catalog_term(string $lang, string $key): string
{
    $terms = [
        'tr'=>['summary'=>'Profesyonel dijital ürün; güvenli lisans, güncellemeler ve müşteri teslimi içerir.','contact'=>'Bilgi al','all_categories'=>'Tüm kategoriler','type'=>'Tür','category'=>'Kategori','tag'=>'Etiket','sort'=>'Sıralama','search_title'=>'Marketplace araması'],
        'en'=>['summary'=>'Professional digital product with secure licensing, updates and customer delivery.','contact'=>'Contact us','all_categories'=>'All categories','type'=>'Type','category'=>'Category','tag'=>'Tag','sort'=>'Sort','search_title'=>'Marketplace search'],
        'de'=>['summary'=>'Professionelles digitales Produkt mit sicherer Lizenzierung, Updates und Kundenzustellung.','contact'=>'Kontakt','all_categories'=>'Alle Kategorien','type'=>'Typ','category'=>'Kategorie','sort'=>'Sortierung','search_title'=>'Marktplatzsuche'],
        'fr'=>['summary'=>'Produit numérique professionnel avec licences sécurisées, mises à jour et livraison client.','contact'=>'Nous contacter','all_categories'=>'Toutes les catégories','type'=>'Type','category'=>'Catégorie','sort'=>'Tri','search_title'=>'Recherche marketplace'],
        'es'=>['summary'=>'Producto digital profesional con licencias seguras, actualizaciones y entrega al cliente.','contact'=>'Contactar','all_categories'=>'Todas las categorías','type'=>'Tipo','category'=>'Categoría','sort'=>'Ordenar','search_title'=>'Búsqueda del marketplace'],
        'it'=>['summary'=>'Prodotto digitale professionale con licenze sicure, aggiornamenti e consegna al cliente.','contact'=>'Contattaci','all_categories'=>'Tutte le categorie','type'=>'Tipo','category'=>'Categoria','sort'=>'Ordina','search_title'=>'Ricerca nel marketplace'],
        'pt'=>['summary'=>'Produto digital profissional com licenciamento seguro, atualizações e entrega ao cliente.','contact'=>'Contactar','all_categories'=>'Todas as categorias','type'=>'Tipo','category'=>'Categoria','sort'=>'Ordenar','search_title'=>'Pesquisa no marketplace'],
        'nl'=>['summary'=>'Professioneel digitaal product met veilige licenties, updates en levering aan klanten.','contact'=>'Neem contact op','all_categories'=>'Alle categorieën','type'=>'Type','category'=>'Categorie','sort'=>'Sorteren','search_title'=>'Marktplaats zoeken'],
        'ru'=>['summary'=>'Профессиональный цифровой продукт с безопасными лицензиями, обновлениями и доставкой клиенту.','contact'=>'Связаться','all_categories'=>'Все категории','type'=>'Тип','category'=>'Категория','sort'=>'Сортировка','search_title'=>'Поиск на маркетплейсе'],
        'ar'=>['summary'=>'منتج رقمي احترافي مع تراخيص آمنة وتحديثات وتسليم للعميل.','contact'=>'تواصل معنا','all_categories'=>'كل الفئات','type'=>'النوع','category'=>'الفئة','sort'=>'ترتيب','search_title'=>'بحث في السوق'],
        'fa'=>['summary'=>'محصول دیجیتال حرفه‌ای با مجوز امن، به‌روزرسانی و تحویل به مشتری.','contact'=>'تماس با ما','all_categories'=>'همه دسته‌ها','type'=>'نوع','category'=>'دسته‌بندی','sort'=>'مرتب‌سازی','search_title'=>'جستجو در بازار'],
        'zh'=>['summary'=>'提供安全许可、更新和客户交付的专业数字产品。','contact'=>'联系我们','all_categories'=>'所有类别','type'=>'类型','category'=>'类别','sort'=>'排序','search_title'=>'市场搜索'],
        'ja'=>['summary'=>'安全なライセンス、更新、顧客向け納品に対応したプロ向けデジタル製品。','contact'=>'お問い合わせ','all_categories'=>'すべてのカテゴリ','type'=>'種類','category'=>'カテゴリ','sort'=>'並べ替え','search_title'=>'マーケット検索'],
        'ko'=>['summary'=>'안전한 라이선스, 업데이트와 고객 전달을 제공하는 전문 디지털 제품입니다.','contact'=>'문의하기','all_categories'=>'모든 카테고리','type'=>'유형','category'=>'카테고리','sort'=>'정렬','search_title'=>'마켓플레이스 검색'],
    ];
    $compatibility = [
        'tr'=>['compatibility'=>'Uyumluluk ve gereksinimler','php'=>'PHP sürümü','extensions'=>'Gerekli PHP uzantıları','database'=>'Veritabanı','cms'=>'CMS sürümü','migration'=>'Migration sürümü'],
        'en'=>['compatibility'=>'Compatibility and requirements','php'=>'PHP version','extensions'=>'Required PHP extensions','database'=>'Database','cms'=>'CMS version','migration'=>'Migration version'],
        'de'=>['compatibility'=>'Kompatibilität und Anforderungen','php'=>'PHP-Version','extensions'=>'Erforderliche PHP-Erweiterungen','database'=>'Datenbank','cms'=>'CMS-Version','migration'=>'Migrationsversion'],
        'fr'=>['compatibility'=>'Compatibilité et prérequis','php'=>'Version PHP','extensions'=>'Extensions PHP requises','database'=>'Base de données','cms'=>'Version du CMS','migration'=>'Version de migration'],
        'es'=>['compatibility'=>'Compatibilidad y requisitos','php'=>'Versión de PHP','extensions'=>'Extensiones PHP necesarias','database'=>'Base de datos','cms'=>'Versión del CMS','migration'=>'Versión de migración'],
        'it'=>['compatibility'=>'Compatibilità e requisiti','php'=>'Versione PHP','extensions'=>'Estensioni PHP richieste','database'=>'Database','cms'=>'Versione CMS','migration'=>'Versione migrazione'],
        'pt'=>['compatibility'=>'Compatibilidade e requisitos','php'=>'Versão do PHP','extensions'=>'Extensões PHP necessárias','database'=>'Base de dados','cms'=>'Versão do CMS','migration'=>'Versão da migração'],
        'ru'=>['compatibility'=>'Совместимость и требования','php'=>'Версия PHP','extensions'=>'Требуемые расширения PHP','database'=>'База данных','cms'=>'Версия CMS','migration'=>'Версия миграции'],
        'ar'=>['compatibility'=>'التوافق والمتطلبات','php'=>'إصدار PHP','extensions'=>'امتدادات PHP المطلوبة','database'=>'قاعدة البيانات','cms'=>'إصدار CMS','migration'=>'إصدار الترحيل'],
        'fa'=>['compatibility'=>'سازگاری و نیازمندی‌ها','php'=>'نسخه PHP','extensions'=>'افزونه‌های ضروری PHP','database'=>'پایگاه داده','cms'=>'نسخه CMS','migration'=>'نسخه مهاجرت'],
        'nl'=>['compatibility'=>'Compatibiliteit en vereisten','php'=>'PHP-versie','extensions'=>'Vereiste PHP-extensies','database'=>'Database','cms'=>'CMS-versie','migration'=>'Migratieversie'],
        'zh'=>['compatibility'=>'兼容性与要求','php'=>'PHP 版本','extensions'=>'所需 PHP 扩展','database'=>'数据库','cms'=>'CMS 版本','migration'=>'迁移版本'],
        'ja'=>['compatibility'=>'互換性と要件','php'=>'PHP バージョン','extensions'=>'必要な PHP 拡張機能','database'=>'データベース','cms'=>'CMS バージョン','migration'=>'マイグレーションバージョン'],
        'ko'=>['compatibility'=>'호환성 및 요구 사항','php'=>'PHP 버전','extensions'=>'필수 PHP 확장','database'=>'데이터베이스','cms'=>'CMS 버전','migration'=>'마이그레이션 버전'],
    ];
    $releaseLabels = [
        'tr'=>['releases'=>'Sürüm geçmişi','version'=>'Sürüm','published'=>'Yayınlandı','changelog'=>'Değişiklikler','no_releases'=>'Yayınlanmış sürüm bulunmuyor.'],
        'en'=>['releases'=>'Release history','version'=>'Version','published'=>'Published','changelog'=>'Changelog','no_releases'=>'No published releases yet.'],
        'de'=>['releases'=>'Versionsverlauf','version'=>'Version','published'=>'Veröffentlicht','changelog'=>'Änderungen','no_releases'=>'Noch keine Version veröffentlicht.'],
        'fr'=>['releases'=>'Historique des versions','version'=>'Version','published'=>'Publié','changelog'=>'Modifications','no_releases'=>'Aucune version publiée.'],
        'es'=>['releases'=>'Historial de versiones','version'=>'Versión','published'=>'Publicado','changelog'=>'Cambios','no_releases'=>'Aún no hay versiones publicadas.'],
        'it'=>['releases'=>'Cronologia versioni','version'=>'Versione','published'=>'Pubblicato','changelog'=>'Modifiche','no_releases'=>'Nessuna versione pubblicata.'],
        'pt'=>['releases'=>'Histórico de versões','version'=>'Versão','published'=>'Publicado','changelog'=>'Alterações','no_releases'=>'Ainda não existem versões publicadas.'],
        'nl'=>['releases'=>'Versiegeschiedenis','version'=>'Versie','published'=>'Gepubliceerd','changelog'=>'Wijzigingen','no_releases'=>'Nog geen releases gepubliceerd.'],
        'ru'=>['releases'=>'История версий','version'=>'Версия','published'=>'Опубликовано','changelog'=>'Изменения','no_releases'=>'Опубликованных версий пока нет.'],
        'ar'=>['releases'=>'سجل الإصدارات','version'=>'الإصدار','published'=>'نُشر','changelog'=>'التغييرات','no_releases'=>'لا توجد إصدارات منشورة بعد.'],
        'fa'=>['releases'=>'تاریخچه نسخه‌ها','version'=>'نسخه','published'=>'منتشرشده','changelog'=>'تغییرات','no_releases'=>'هنوز نسخه‌ای منتشر نشده است.'],
        'zh'=>['releases'=>'版本历史','version'=>'版本','published'=>'已发布','changelog'=>'更新内容','no_releases'=>'暂无已发布版本。'],
        'ja'=>['releases'=>'リリース履歴','version'=>'バージョン','published'=>'公開日','changelog'=>'変更内容','no_releases'=>'公開済みリリースはまだありません。'],
        'ko'=>['releases'=>'릴리스 기록','version'=>'버전','published'=>'게시됨','changelog'=>'변경 사항','no_releases'=>'게시된 릴리스가 아직 없습니다.'],
    ];
    $taxonomyLabels = ['de'=>'Tag','fr'=>'Étiquette','es'=>'Etiqueta','it'=>'Tag','pt'=>'Etiqueta','nl'=>'Tag','ru'=>'Тег','ar'=>'وسم','fa'=>'برچسب','zh'=>'标签','ja'=>'タグ','ko'=>'태그'];
    $stateLabels = ['tr'=>'Yayın paketi hazırlanıyor','en'=>'Release package is being prepared','de'=>'Release-Paket wird vorbereitet','fr'=>'Le package de release est en préparation','es'=>'El paquete de lanzamiento está en preparación','it'=>'Il pacchetto di rilascio è in preparazione','pt'=>'O pacote de release está a ser preparado','nl'=>'Releasepakket wordt voorbereid','ru'=>'Пакет релиза готовится','ar'=>'حزمة الإصدار قيد الإعداد','fa'=>'بسته انتشار در حال آماده‌سازی است','zh'=>'发布包正在准备中','ja'=>'リリースパッケージを準備中','ko'=>'릴리스 패키지를 준비 중입니다'];
    if ($key === 'tag' && isset($taxonomyLabels[$lang])) return $taxonomyLabels[$lang];
    if ($key === 'preview_only') return $stateLabels[$lang] ?? $stateLabels['en'];
    return $terms[$lang][$key] ?? $compatibility[$lang][$key] ?? $releaseLabels[$lang][$key] ?? $terms['en'][$key] ?? $compatibility['en'][$key] ?? $releaseLabels['en'][$key] ?? $key;
}

function catalog_plan_term(string $lang, string $key, string $value = ''): string
{
    $terms = [
        'tr'=>['domain'=>'alan adı','single_site'=>'Tek site','multi_site'=>'Çoklu site','unlimited'=>'Sınırsız','subscription'=>'Abonelik','lifetime'=>'Ömür boyu','monthly'=>'Aylık','yearly'=>'Yıllık'],
        'en'=>['domain'=>'domain','single_site'=>'Single site','multi_site'=>'Multi-site','unlimited'=>'Unlimited','subscription'=>'Subscription','lifetime'=>'Lifetime','monthly'=>'Monthly','yearly'=>'Yearly'],
        'de'=>['domain'=>'Domain','single_site'=>'Einzelne Website','multi_site'=>'Mehrere Websites','unlimited'=>'Unbegrenzt','subscription'=>'Abonnement','lifetime'=>'Lebenslang','monthly'=>'Monatlich','yearly'=>'Jährlich'],
        'fr'=>['domain'=>'domaine','single_site'=>'Site unique','multi_site'=>'Sites multiples','unlimited'=>'Illimité','subscription'=>'Abonnement','lifetime'=>'À vie','monthly'=>'Mensuel','yearly'=>'Annuel'],
        'es'=>['domain'=>'dominio','single_site'=>'Sitio único','multi_site'=>'Varios sitios','unlimited'=>'Ilimitado','subscription'=>'Suscripción','lifetime'=>'De por vida','monthly'=>'Mensual','yearly'=>'Anual'],
        'it'=>['domain'=>'dominio','single_site'=>'Sito singolo','multi_site'=>'Siti multipli','unlimited'=>'Illimitato','subscription'=>'Abbonamento','lifetime'=>'A vita','monthly'=>'Mensile','yearly'=>'Annuale'],
        'pt'=>['domain'=>'domínio','single_site'=>'Site único','multi_site'=>'Vários sites','unlimited'=>'Ilimitado','subscription'=>'Subscrição','lifetime'=>'Vitalícia','monthly'=>'Mensal','yearly'=>'Anual'],
        'ru'=>['domain'=>'домен','single_site'=>'Один сайт','multi_site'=>'Несколько сайтов','unlimited'=>'Без ограничений','subscription'=>'Подписка','lifetime'=>'Бессрочная','monthly'=>'Ежемесячно','yearly'=>'Ежегодно'],
        'ar'=>['domain'=>'نطاق','single_site'=>'موقع واحد','multi_site'=>'عدة مواقع','unlimited'=>'غير محدود','subscription'=>'اشتراك','lifetime'=>'مدى الحياة','monthly'=>'شهري','yearly'=>'سنوي'],
        'fa'=>['domain'=>'دامنه','single_site'=>'یک سایت','multi_site'=>'چند سایت','unlimited'=>'نامحدود','subscription'=>'اشتراکی','lifetime'=>'دائمی','monthly'=>'ماهانه','yearly'=>'سالانه'],
        'nl'=>['domain'=>'domein','single_site'=>'Eén site','multi_site'=>'Meerdere sites','unlimited'=>'Onbeperkt','subscription'=>'Abonnement','lifetime'=>'Levenslang','monthly'=>'Maandelijks','yearly'=>'Jaarlijks'],
        'zh'=>['domain'=>'域名','single_site'=>'单站点','multi_site'=>'多站点','unlimited'=>'不限数量','subscription'=>'订阅','lifetime'=>'永久','monthly'=>'每月','yearly'=>'每年'],
        'ja'=>['domain'=>'ドメイン','single_site'=>'単一サイト','multi_site'=>'複数サイト','unlimited'=>'無制限','subscription'=>'サブスクリプション','lifetime'=>'買い切り','monthly'=>'月額','yearly'=>'年額'],
        'ko'=>['domain'=>'도메인','single_site'=>'단일 사이트','multi_site'=>'다중 사이트','unlimited'=>'무제한','subscription'=>'구독','lifetime'=>'영구','monthly'=>'월간','yearly'=>'연간'],
    ];
    $locale = $terms[$lang] ?? $terms['en'];
    if ($key === 'domains') return $locale['domain'];
    return $locale[$value] ?? $value;
}

function catalog_public_route(): void {
    global $path;
    if ($path === '/pricing') {
        $lang = lc_public_locale();
        $copy = [
            'tr'=>['eyebrow'=>'Ürün planları','title'=>'İşletmeniz için doğru lisansı seçin.','lead'=>'Her ürünün yayınlanmış lisans planlarını, kapsamını ve güncelleme haklarını karşılaştırın.','plans'=>'Yayınlanmış planlar','empty'=>'Henüz yayınlanmış plan yok.'],
            'en'=>['eyebrow'=>'Product plans','title'=>'Choose the right licence for your business.','lead'=>'Compare each product’s published plans, scope and update entitlement before you buy.','plans'=>'Published plans','empty'=>'No published plans yet.'],
            'de'=>['eyebrow'=>'Produktpläne','title'=>'Wählen Sie die passende Lizenz für Ihr Unternehmen.','lead'=>'Vergleichen Sie veröffentlichte Pläne, Umfang und Update-Rechte jedes Produkts.','plans'=>'Veröffentlichte Pläne','empty'=>'Noch keine Pläne veröffentlicht.'],
            'fr'=>['eyebrow'=>'Plans produit','title'=>'Choisissez la licence adaptée à votre activité.','lead'=>'Comparez les plans publiés, leur périmètre et les droits de mise à jour avant l’achat.','plans'=>'Plans publiés','empty'=>'Aucun plan publié pour le moment.'],
            'es'=>['eyebrow'=>'Planes de producto','title'=>'Elige la licencia adecuada para tu negocio.','lead'=>'Compara los planes publicados, su alcance y las actualizaciones incluidas antes de comprar.','plans'=>'Planes publicados','empty'=>'Aún no hay planes publicados.'],
            'it'=>['eyebrow'=>'Piani prodotto','title'=>'Scegli la licenza giusta per la tua attività.','lead'=>'Confronta i piani pubblicati, l’ambito e gli aggiornamenti inclusi prima dell’acquisto.','plans'=>'Piani pubblicati','empty'=>'Nessun piano pubblicato.'],
            'pt'=>['eyebrow'=>'Planos do produto','title'=>'Escolha a licença certa para a sua empresa.','lead'=>'Compare os planos publicados, o âmbito e as atualizações incluídas antes de comprar.','plans'=>'Planos publicados','empty'=>'Ainda não existem planos publicados.'],
            'ru'=>['eyebrow'=>'Тарифы продукта','title'=>'Выберите подходящую лицензию для бизнеса.','lead'=>'Сравните опубликованные планы, их возможности и право на обновления перед покупкой.','plans'=>'Опубликованные планы','empty'=>'Опубликованных планов пока нет.'],
            'ar'=>['eyebrow'=>'خطط المنتجات','title'=>'اختر الترخيص المناسب لنشاطك.','lead'=>'قارن الخطط المنشورة ونطاقها وتحديثاتها قبل الشراء.','plans'=>'الخطط المنشورة','empty'=>'لا توجد خطط منشورة بعد.'],
            'fa'=>['eyebrow'=>'طرح‌های محصول','title'=>'مجوز مناسب کسب‌وکار خود را انتخاب کنید.','lead'=>'پیش از خرید، طرح‌های منتشرشده، دامنه استفاده و حق به‌روزرسانی را مقایسه کنید.','plans'=>'طرح‌های منتشرشده','empty'=>'هنوز طرحی منتشر نشده است.'],
            'nl'=>['eyebrow'=>'Productplannen','title'=>'Kies de juiste licentie voor uw bedrijf.','lead'=>'Vergelijk gepubliceerde plannen, omvang en inbegrepen updates voordat u koopt.','plans'=>'Gepubliceerde plannen','empty'=>'Nog geen plannen gepubliceerd.'],
            'zh'=>['eyebrow'=>'产品方案','title'=>'为您的企业选择合适的许可证。','lead'=>'购买前比较每个产品已发布的方案、范围和更新权益。','plans'=>'已发布方案','empty'=>'暂时没有已发布方案。'],
            'ja'=>['eyebrow'=>'製品プラン','title'=>'ビジネスに合ったライセンスを選択。','lead'=>'購入前に、各製品の公開プラン、対象範囲、更新権利を比較できます。','plans'=>'公開済みプラン','empty'=>'公開済みプランはまだありません。'],
            'ko'=>['eyebrow'=>'제품 플랜','title'=>'비즈니스에 맞는 라이선스를 선택하세요.','lead'=>'구매 전에 공개된 플랜의 범위와 업데이트 권한을 비교하세요.','plans'=>'공개된 플랜','empty'=>'아직 공개된 플랜이 없습니다.'],
        ][$lang] ?? [];
        $displayCurrency = catalog_display_currency($lang);
        $plans = q("SELECT p.slug,p.kind,t.title,lp.code,lp.name,lp.license_type,lp.billing_period,lp.domain_limit,lp.currency,lp.amount_minor FROM license_plans lp JOIN products p ON p.id=lp.product_id AND p.status='published' JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE lp.status='published' ORDER BY p.updated_at DESC,lp.amount_minor,lp.id", [$lang])->fetchAll();
        $cards = '';
        foreach ($plans as $plan) {
            $amount = catalog_display_amount((int)$plan['amount_minor'], (string)$plan['currency'], $displayCurrency);
            $name = function_exists('lc_plan_name') ? lc_plan_name($plan, $lang) : (string)$plan['name'];
            $cards .= '<article class="card"><span class="eyebrow">'.esc(strtoupper((string)$plan['kind'])).'</span><h2>'.esc((string)$plan['title']).'</h2><h3>'.esc($name).'</h3><p class="product-price">'.esc($displayCurrency).' '.esc(number_format($amount / 100, 2)).'</p><p>'.esc(catalog_plan_term($lang, 'license_type', (string)$plan['license_type'])).' · '.esc(catalog_plan_term($lang, 'billing_period', (string)$plan['billing_period'])).' · '.(int)$plan['domain_limit'].' '.esc(catalog_plan_term($lang, 'domains')).'</p><p style="margin-top:16px"><a class="button" href="./product?slug='.rawurlencode((string)$plan['slug']).'&lang='.rawurlencode($lang).'">'.esc(catalog_label($lang, 'view')).'</a></p></article>';
        }
        $body = '<section class="hero"><span class="eyebrow">'.esc($copy['eyebrow']).'</span><h1>'.esc($copy['title']).'</h1><p>'.esc($copy['lead']).'</p>'.catalog_currency_switcher($lang).'</section><h2 class="section-title">'.esc($copy['plans']).'</h2><section class="grid">'.($cards !== '' ? $cards : '<article class="card"><h2>'.esc($copy['empty']).'</h2></article>').'</section>';
        layout($copy['title'], $body, true);
    }
    if ($path === '/shop') {
        $lang = lc_public_locale();
        $copy = [
            'tr'=>['eyebrow'=>'Global yazılım pazarı','title'=>'Her sektör için yazılım, tema ve dijital araçlar.','lead'=>'Profesyonel ürünleri keşfedin, sürümleri karşılaştırın ve güvenli lisans, sürümlü indirme ve güncellemelerle tek müşteri hesabından satın alın.','browse'=>'Pazarı keşfet →','signin'=>'Müşteri girişi','software'=>'Her sektör için yazılım','softwareText'=>'İş uygulamaları, özel çözümler ve kullanıma hazır dijital ürünler.','themes'=>'Premium web temaları','themesText'=>'Demo, dokümantasyon, sürüm ve çoklu dil desteğine sahip duyarlı temalar.','licences'=>'Lisans ve güncellemeler','licencesText'=>'Alan adına bağlı lisans, indirme, sürüm geçmişi ve destek tek yerde.','latest'=>'Son ürünler','empty'=>'Ürünler hazırlanıyor','emptyText'=>'Yayınlanan yazılım, tema ve eklentiler burada görünecek.','view'=>'Ürünü incele →','compare'=>'Karşılaştır'],
            'en'=>['eyebrow'=>'Global software marketplace','title'=>'Software, themes and tools for every industry.','lead'=>'Discover professional products, compare editions and buy with secure licensing, versioned downloads and managed updates from one customer account.','browse'=>'Explore the marketplace →','signin'=>'Customer sign in','software'=>'Software for every sector','softwareText'=>'Business applications, niche solutions and ready-to-launch digital products.','themes'=>'Premium website themes','themesText'=>'Responsive themes with demos, documentation, releases and localization.','licences'=>'Licences and updates','licencesText'=>'Domain-bound licences, buyer downloads, release history and support in one place.','latest'=>'Latest products','empty'=>'Products are coming soon','emptyText'=>'Published software, themes and add-ons will appear here.','view'=>'View product →','compare'=>'Compare'],
            'de'=>['eyebrow'=>'Globaler Software-Marktplatz','title'=>'Software, Themes und Tools für jede Branche.','lead'=>'Entdecken Sie professionelle Produkte, vergleichen Sie Editionen und kaufen Sie mit sicheren Lizenzen, Versionen und Updates über ein Kundenkonto.','browse'=>'Marktplatz entdecken →','signin'=>'Kundenanmeldung','software'=>'Software für jede Branche','softwareText'=>'Geschäftsanwendungen, Speziallösungen und sofort einsetzbare digitale Produkte.','themes'=>'Premium-Website-Themes','themesText'=>'Responsive Themes mit Demos, Dokumentation, Releases und Lokalisierung.','licences'=>'Lizenzen und Updates','licencesText'=>'Domaingebundene Lizenzen, Downloads, Versionshistorie und Support an einem Ort.','latest'=>'Neueste Produkte','empty'=>'Produkte folgen bald','emptyText'=>'Veröffentlichte Software, Themes und Add-ons erscheinen hier.','view'=>'Produkt ansehen →','compare'=>'Vergleichen'],
            'fr'=>['eyebrow'=>'Marketplace mondial de logiciels','title'=>'Logiciels, thèmes et outils pour tous les secteurs.','lead'=>'Découvrez des produits professionnels, comparez les éditions et achetez avec licences sécurisées et mises à jour depuis un seul compte.','browse'=>'Explorer le marketplace →','signin'=>'Connexion client','software'=>'Des logiciels pour chaque secteur','softwareText'=>'Applications métier, solutions spécialisées et produits numériques prêts à l’emploi.','themes'=>'Thèmes web premium','themesText'=>'Thèmes responsives avec démos, documentation, versions et localisation.','licences'=>'Licences et mises à jour','licencesText'=>'Licences par domaine, téléchargements, historique des versions et support réunis.','latest'=>'Derniers produits','empty'=>'Produits bientôt disponibles','emptyText'=>'Les logiciels, thèmes et extensions publiés apparaîtront ici.','view'=>'Voir le produit →','compare'=>'Comparer'],
            'es'=>['eyebrow'=>'Mercado global de software','title'=>'Software, temas y herramientas para cualquier sector.','lead'=>'Descubre productos profesionales, compara ediciones y compra con licencias seguras y actualizaciones desde una sola cuenta.','browse'=>'Explorar el marketplace →','signin'=>'Acceso de cliente','software'=>'Software para cada sector','softwareText'=>'Aplicaciones empresariales, soluciones especializadas y productos digitales listos para usar.','themes'=>'Temas web premium','themesText'=>'Temas adaptables con demos, documentación, versiones y localización.','licences'=>'Licencias y actualizaciones','licencesText'=>'Licencias por dominio, descargas, historial de versiones y soporte en un solo lugar.','latest'=>'Últimos productos','empty'=>'Próximamente habrá productos','emptyText'=>'El software, los temas y los complementos publicados aparecerán aquí.','view'=>'Ver producto →','compare'=>'Comparar'],
            'it'=>['eyebrow'=>'Marketplace globale del software','title'=>'Software, temi e strumenti per ogni settore.','lead'=>'Scopri prodotti professionali, confronta le edizioni e acquista con licenze sicure e aggiornamenti da un unico account.','browse'=>'Esplora il marketplace →','signin'=>'Accesso cliente','software'=>'Software per ogni settore','softwareText'=>'Applicazioni aziendali, soluzioni specialistiche e prodotti digitali pronti all’uso.','themes'=>'Temi web premium','themesText'=>'Temi responsive con demo, documentazione, release e localizzazione.','licences'=>'Licenze e aggiornamenti','licencesText'=>'Licenze per dominio, download, cronologia versioni e supporto in un unico spazio.','latest'=>'Ultimi prodotti','empty'=>'Prodotti in arrivo','emptyText'=>'Software, temi e componenti pubblicati appariranno qui.','view'=>'Vedi prodotto →','compare'=>'Confronta'],
            'pt'=>['eyebrow'=>'Marketplace global de software','title'=>'Software, temas e ferramentas para todos os setores.','lead'=>'Encontre produtos profissionais, compare edições e compre com licenças seguras e atualizações numa única conta.','browse'=>'Explorar o marketplace →','signin'=>'Entrar como cliente','software'=>'Software para todos os setores','softwareText'=>'Aplicações empresariais, soluções especializadas e produtos digitais prontos a usar.','themes'=>'Temas web premium','themesText'=>'Temas responsivos com demos, documentação, versões e localização.','licences'=>'Licenças e atualizações','licencesText'=>'Licenças por domínio, downloads, histórico de versões e suporte num só lugar.','latest'=>'Produtos mais recentes','empty'=>'Produtos em breve','emptyText'=>'Software, temas e extensões publicados aparecerão aqui.','view'=>'Ver produto →','compare'=>'Comparar'],
            'ru'=>['eyebrow'=>'Глобальный рынок программного обеспечения','title'=>'Программы, темы и инструменты для любой отрасли.','lead'=>'Находите профессиональные продукты, сравнивайте версии и покупайте с безопасными лицензиями и обновлениями из одного аккаунта.','browse'=>'Открыть маркетплейс →','signin'=>'Вход клиента','software'=>'ПО для любой отрасли','softwareText'=>'Бизнес-приложения, специализированные решения и готовые цифровые продукты.','themes'=>'Премиум-темы сайтов','themesText'=>'Адаптивные темы с демо, документацией, релизами и локализацией.','licences'=>'Лицензии и обновления','licencesText'=>'Лицензии по доменам, загрузки, история версий и поддержка в одном месте.','latest'=>'Новые продукты','empty'=>'Продукты скоро появятся','emptyText'=>'Опубликованные программы, темы и дополнения появятся здесь.','view'=>'Открыть продукт →','compare'=>'Сравнить'],
            'ar'=>['eyebrow'=>'سوق عالمي للبرمجيات','title'=>'برمجيات وقوالب وأدوات لكل القطاعات.','lead'=>'اكتشف المنتجات الاحترافية وقارن الإصدارات واشترِ بتراخيص آمنة وتحديثات من حساب عميل واحد.','browse'=>'استكشف السوق →','signin'=>'دخول العميل','software'=>'برمجيات لكل قطاع','softwareText'=>'تطبيقات أعمال وحلول متخصصة ومنتجات رقمية جاهزة للإطلاق.','themes'=>'قوالب مواقع مميزة','themesText'=>'قوالب متجاوبة مع عروض توضيحية ووثائق وإصدارات وترجمة.','licences'=>'التراخيص والتحديثات','licencesText'=>'تراخيص مرتبطة بالنطاق وتنزيلات وسجل إصدارات ودعم في مكان واحد.','latest'=>'أحدث المنتجات','empty'=>'المنتجات قادمة قريباً','emptyText'=>'ستظهر هنا البرمجيات والقوالب والإضافات المنشورة.','view'=>'عرض المنتج →','compare'=>'مقارنة'],
            'fa'=>['eyebrow'=>'بازار جهانی نرم‌افزار','title'=>'نرم‌افزار، قالب و ابزار برای هر صنعت.','lead'=>'محصولات حرفه‌ای را پیدا کنید، نسخه‌ها را مقایسه کنید و با مجوز امن و به‌روزرسانی از یک حساب خرید کنید.','browse'=>'مشاهده بازار →','signin'=>'ورود مشتری','software'=>'نرم‌افزار برای هر صنعت','softwareText'=>'برنامه‌های تجاری، راهکارهای تخصصی و محصولات دیجیتال آماده استفاده.','themes'=>'قالب‌های حرفه‌ای سایت','themesText'=>'قالب‌های واکنش‌گرا با دمو، مستندات، نسخه‌ها و بومی‌سازی.','licences'=>'مجوزها و به‌روزرسانی‌ها','licencesText'=>'مجوز دامنه، دانلود، تاریخچه نسخه و پشتیبانی در یک مکان.','latest'=>'جدیدترین محصولات','empty'=>'محصولات به‌زودی ارائه می‌شوند','emptyText'=>'نرم‌افزارها، قالب‌ها و افزونه‌های منتشرشده اینجا نمایش داده می‌شوند.','view'=>'مشاهده محصول →','compare'=>'مقایسه'],
            'nl'=>['eyebrow'=>'Wereldwijde softwaremarktplaats','title'=>'Software, thema’s en tools voor elke sector.','lead'=>'Ontdek professionele producten, vergelijk edities en koop met veilige licenties en updates vanuit één klantaccount.','browse'=>'Bekijk de marktplaats →','signin'=>'Klant aanmelden','software'=>'Software voor elke sector','softwareText'=>'Bedrijfstoepassingen, gespecialiseerde oplossingen en direct inzetbare digitale producten.','themes'=>'Premium websitethema’s','themesText'=>'Responsieve thema’s met demo’s, documentatie, releases en lokalisatie.','licences'=>'Licenties en updates','licencesText'=>'Domeinlicenties, downloads, versiehistorie en support op één plek.','latest'=>'Nieuwste producten','empty'=>'Producten volgen binnenkort','emptyText'=>'Gepubliceerde software, thema’s en add-ons verschijnen hier.','view'=>'Product bekijken →','compare'=>'Vergelijken'],
            'zh'=>['eyebrow'=>'全球软件市场','title'=>'面向各行业的软件、主题和工具。','lead'=>'发现专业产品、比较版本，并通过一个客户账户购买安全许可和托管更新。','browse'=>'探索市场 →','signin'=>'客户登录','software'=>'适合各行业的软件','softwareText'=>'商业应用、专业解决方案和开箱即用的数字产品。','themes'=>'高级网站主题','themesText'=>'支持演示、文档、版本发布和本地化的响应式主题。','licences'=>'许可证与更新','licencesText'=>'域名绑定许可证、下载、版本记录和支持集中管理。','latest'=>'最新产品','empty'=>'产品即将上线','emptyText'=>'已发布的软件、主题和插件将在此显示。','view'=>'查看产品 →','compare'=>'比较'],
            'ja'=>['eyebrow'=>'グローバルソフトウェアマーケット','title'=>'あらゆる業界向けのソフトウェア、テーマ、ツール。','lead'=>'プロ向け製品を探し、エディションを比較し、安全なライセンスと更新を一つのアカウントで購入できます。','browse'=>'マーケットを見る →','signin'=>'顧客ログイン','software'=>'あらゆる業界のソフトウェア','softwareText'=>'業務アプリ、専門ソリューション、すぐ使えるデジタル製品。','themes'=>'プレミアムWebテーマ','themesText'=>'デモ、ドキュメント、リリース、ローカライズ対応のレスポンシブテーマ。','licences'=>'ライセンスと更新','licencesText'=>'ドメインライセンス、ダウンロード、更新履歴、サポートを一元管理。','latest'=>'最新製品','empty'=>'製品は近日公開','emptyText'=>'公開されたソフトウェア、テーマ、アドオンがここに表示されます。','view'=>'製品を見る →','compare'=>'比較'],
            'ko'=>['eyebrow'=>'글로벌 소프트웨어 마켓플레이스','title'=>'모든 산업을 위한 소프트웨어, 테마와 도구.','lead'=>'전문 제품을 찾고 에디션을 비교하며 하나의 고객 계정에서 안전한 라이선스와 업데이트를 구매하세요.','browse'=>'마켓플레이스 둘러보기 →','signin'=>'고객 로그인','software'=>'모든 산업을 위한 소프트웨어','softwareText'=>'비즈니스 애플리케이션, 전문 솔루션과 바로 사용할 수 있는 디지털 제품.','themes'=>'프리미엄 웹사이트 테마','themesText'=>'데모, 문서, 릴리스와 현지화를 지원하는 반응형 테마.','licences'=>'라이선스 및 업데이트','licencesText'=>'도메인 라이선스, 다운로드, 버전 기록과 지원을 한곳에서 관리합니다.','latest'=>'최신 제품','empty'=>'제품이 곧 공개됩니다','emptyText'=>'게시된 소프트웨어, 테마와 애드온이 여기에 표시됩니다.','view'=>'제품 보기 →','compare'=>'비교']
        ][$lang] ?? [];
        $displayCurrency = catalog_display_currency($lang);
        $products = q("SELECT p.id,p.slug,p.name,p.kind,p.updated_at,t.title,t.summary,COALESCE((SELECT pp.amount_minor FROM product_prices pp WHERE pp.product_id=p.id AND pp.currency=? AND pp.active=1 LIMIT 1),(SELECT MIN(pp.amount_minor) FROM product_prices pp WHERE pp.product_id=p.id AND pp.active=1),0) AS amount_minor,COALESCE((SELECT pp.currency FROM product_prices pp WHERE pp.product_id=p.id AND pp.currency=? AND pp.active=1 LIMIT 1),(SELECT pp.currency FROM product_prices pp WHERE pp.product_id=p.id AND pp.active=1 ORDER BY pp.amount_minor LIMIT 1),'EUR') AS currency FROM products p JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE p.status='published' ORDER BY p.updated_at DESC,p.id DESC LIMIT 24", [$displayCurrency,$displayCurrency,$lang])->fetchAll();
        $cards = '';
        foreach ($products as $product) {
            $title = (string)($product['title'] ?: $product['name']);
            $summary = (string)($product['summary'] ?: catalog_term($lang, 'summary'));
            $displayAmount = catalog_display_amount((int)$product['amount_minor'], (string)$product['currency'], $displayCurrency);
            $price = $displayAmount > 0 ? '<strong>'.esc($displayCurrency).' '.esc(number_format($displayAmount / 100, 2)).'</strong>' : '<strong>'.esc(catalog_term($lang, 'contact')).'</strong>';
            $cards .= '<article class="card product-card"><span class="eyebrow">'.esc(strtoupper((string)$product['kind'])).'</span><h2>'.esc($title).'</h2><p>'.esc($summary).'</p><p class="product-price">'.$price.'</p><div class="actions"><a class="button" href="./product?slug='.rawurlencode((string)$product['slug']).'">'.esc($copy['view']).'</a><a class="button ghost" href="./compare/add?product_id='.(int)$product['id'].'">'.esc($copy['compare']).'</a></div></article>';
        }
        $schemaItems = [];
        foreach ($products as $position => $product) $schemaItems[] = ['@type'=>'ListItem','position'=>$position + 1,'item'=>['@type'=>$product['kind'] === 'application' ? 'SoftwareApplication' : 'Product','name'=>(string)($product['title'] ?: $product['name']),'url'=>rtrim(lc_env('APP_URL','https://product.tofidesk.com'),'/').'/product?slug='.rawurlencode((string)$product['slug'])]];
        $schema = ['@context'=>'https://schema.org','@type'=>'ItemList','name'=>'TofiDesk Marketplace','itemListElement'=>$schemaItems];
        $body = '<script type="application/ld+json">'.json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script><section class="hero"><span class="eyebrow">'.esc($copy['eyebrow']).'</span><h1>'.esc($copy['title']).'</h1><p>'.esc($copy['lead']).'</p>'.catalog_currency_switcher($lang).'<div class="actions"><a class="button" href="./search">'.esc($copy['browse']).'</a><a class="button ghost" href="./account/login">'.esc($copy['signin']).'</a></div></section><section class="grid marketplace-highlights"><article class="card"><div class="feature-icon">◈</div><h2>'.esc($copy['software']).'</h2><p>'.esc($copy['softwareText']).'</p></article><article class="card"><div class="feature-icon">⌘</div><h2>'.esc($copy['themes']).'</h2><p>'.esc($copy['themesText']).'</p></article><article class="card"><div class="feature-icon">✦</div><h2>'.esc($copy['licences']).'</h2><p>'.esc($copy['licencesText']).'</p></article></section><h2 class="section-title">'.esc($copy['latest']).'</h2><section class="grid product-grid">'.($cards !== '' ? $cards : '<article class="card"><h2>'.esc($copy['empty']).'</h2><p>'.esc($copy['emptyText']).'</p></article>').'</section>';
        layout($copy['eyebrow'], $body, true);
    }
    if ($path === '/compare/add') {
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!one("SELECT id FROM products WHERE id=? AND status='published'", [$productId])) throw new RuntimeException('Product not found.');
        $compare = array_values(array_unique(array_map('intval', (array)($_SESSION['compare_products'] ?? []))));
        if (!in_array($productId, $compare, true)) $compare[] = $productId;
        $_SESSION['compare_products'] = array_slice($compare, -4);
        header('Location: '.($_SERVER['HTTP_REFERER'] ?? './compare')); exit;
    }
    if ($path === '/compare/remove') {
        $productId = (int)($_GET['product_id'] ?? 0);
        $_SESSION['compare_products'] = array_values(array_filter(array_map('intval', (array)($_SESSION['compare_products'] ?? [])), static fn(int $id): bool => $id !== $productId));
        header('Location: ./compare'); exit;
    }
    if ($path === '/compare') {
        $lang = lc_public_locale();
        $ids = array_slice(array_values(array_unique(array_map('intval', (array)($_SESSION['compare_products'] ?? [])))), 0, 4);
        $cards = '';
        foreach ($ids as $productId) {
            $item = one("SELECT p.*,t.title,t.summary,t.description FROM products p JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE p.id=? AND p.status='published'", [$lang,$productId]);
            if (!$item) continue;
            $cards .= '<article class="card"><span class="eyebrow">'.esc(strtoupper($item['kind'])).'</span><h2>'.esc($item['title'] ?: $item['name']).'</h2><p>'.esc($item['summary']).'</p><p>'.nl2br(esc($item['description'])).'</p><a class="button ghost" href="./compare/remove?product_id='.(int)$productId.'">'.esc(catalog_label($lang,'remove')).'</a></article>';
        }
        layout(catalog_label($lang,'comparison'),'<section class="hero"><span class="eyebrow">'.esc(catalog_label($lang,'comparison')).'</span><h1>'.esc(catalog_label($lang,'comparison_title')).'</h1><p>'.esc(catalog_label($lang,'comparison_lead')).'</p></section><section class="grid">'.($cards ?: '<article class="card"><p>'.esc(catalog_label($lang,'empty_compare')).'</p></article>').'</section>', true);
    }
    if ($path === '/account/favorite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $buyerId = (int)($_SESSION['buyer'] ?? 0);
        if ($buyerId <= 0) { header('Location: ./account/login'); exit; }
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!one("SELECT id FROM products WHERE id=? AND status='published'", [$productId])) throw new RuntimeException('Product not found.');
        if (one('SELECT product_id FROM product_favorites WHERE product_id=? AND buyer_id=?', [$productId,$buyerId])) q('DELETE FROM product_favorites WHERE product_id=? AND buyer_id=?', [$productId,$buyerId]);
        else q('INSERT INTO product_favorites(product_id,buyer_id) VALUES(?,?)', [$productId,$buyerId]);
        header('Location: '.($_SERVER['HTTP_REFERER'] ?? './shop')); exit;
    }
    if ($path === '/account/favorites') {
        $buyerId = (int)($_SESSION['buyer'] ?? 0);
        if ($buyerId <= 0) { header('Location: ./account/login'); exit; }
        $lang = lc_public_locale();
        $items = q("SELECT p.slug,p.name,p.kind,t.title,t.summary FROM product_favorites f JOIN products p ON p.id=f.product_id AND p.status='published' JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE f.buyer_id=? ORDER BY f.created_at DESC", [$lang,$buyerId])->fetchAll();
        $cards=''; foreach ($items as $item) $cards .= '<article class="card"><span class="eyebrow">'.esc(strtoupper($item['kind'])).'</span><h2><a href="./product?slug='.rawurlencode($item['slug']).'">'.esc($item['title'] ?: $item['name']).'</a></h2><p>'.esc($item['summary']).'</p></article>';
        layout(catalog_label($lang,'save'),'<h1>'.esc(catalog_label($lang,'save')).'</h1><section class="grid">'.($cards ?: '<article class="card"><p>'.esc(catalog_label($lang,'empty_compare')).'</p></article>').'</section>');
    }
    if ($path === '/product-review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $buyerId = (int)($_SESSION['buyer'] ?? 0); if ($buyerId <= 0) { header('Location: ./account/login'); exit; }
        $productId = (int)($_POST['product_id'] ?? 0); $rating = max(1, min(5, (int)($_POST['rating'] ?? 5))); $title = mb_substr(trim((string)($_POST['title'] ?? '')),0,180); $reviewBody = mb_substr(trim((string)($_POST['body'] ?? '')),0,5000);
        $product = one("SELECT id,slug FROM products WHERE id=? AND status='published'", [$productId]);
        if (!$product || $title === '' || $reviewBody === '') throw new RuntimeException('A product, title and review are required.');
        // Reviews are a buyer benefit, not an anonymous marketing channel. A
        // paid order or order line for this product is required; the review is
        // still queued for editorial moderation before it becomes public.
        $purchased = one("SELECT 1 FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.buyer_id=? AND o.status='paid' AND (o.product_id=? OR oi.product_id=? OR o.product=?) LIMIT 1", [$buyerId,$productId,$productId,$product['slug']]);
        if (!$purchased) throw new RuntimeException('Only verified buyers can review this product.');
        q('INSERT INTO product_reviews(product_id,buyer_id,rating,title,body,status) VALUES(?,?,?,?,?,?) ON CONFLICT(product_id,buyer_id) DO UPDATE SET rating=excluded.rating,title=excluded.title,body=excluded.body,status=\'pending\'',[$productId,$buyerId,$rating,$title,$reviewBody,'pending']);
        header('Location: ./product?id='.$productId.'&review=sent'); exit;
    }
    if ($path === '/search') {
        $lang = lc_public_locale();
        $term = trim((string)($_GET['q'] ?? ''));
        $kind = (string)($_GET['kind'] ?? '');
        $category = strtolower(trim((string)($_GET['category'] ?? '')));
        $tag = strtolower(trim((string)($_GET['tag'] ?? '')));
        $params = [$lang];
        $sql = "SELECT p.id,p.slug,p.name,p.kind,t.title,t.summary FROM products p JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' LEFT JOIN product_metrics pm ON pm.product_id=p.id WHERE p.status='published'";
        if (in_array($kind, ['application','theme','addon'], true)) { $sql .= ' AND p.kind=?'; $params[]=$kind; }
        if ($category !== '') { $sql .= " AND EXISTS (SELECT 1 FROM product_category_links pcl JOIN product_categories pc ON pc.id=pcl.category_id WHERE pcl.product_id=p.id AND pc.slug=?)"; $params[]=$category; }
        if ($tag !== '') { $sql .= " AND EXISTS (SELECT 1 FROM product_tag_links ptl JOIN product_tags pt ON pt.id=ptl.tag_id WHERE ptl.product_id=p.id AND pt.slug=?)"; $params[]=$tag; }
        if ($term !== '') { $sql .= ' AND (t.title LIKE ? OR t.summary LIKE ? OR t.keywords LIKE ?)'; $needle='%'.$term.'%'; array_push($params,$needle,$needle,$needle); }
        $sort = (string)($_GET['sort'] ?? 'new');
        $sql .= $sort === 'popular' ? ' ORDER BY COALESCE(pm.downloads,0) DESC,COALESCE(pm.sales,0) DESC,COALESCE(pm.views,0) DESC,p.id DESC LIMIT 50' : ' ORDER BY p.updated_at DESC,p.id DESC LIMIT 50';
        $results = q($sql,$params)->fetchAll();
        $cards=''; foreach ($results as $item) $cards .= '<article class="card"><span class="eyebrow">'.esc(strtoupper($item['kind'])).'</span><h2><a href="./product?slug='.rawurlencode($item['slug']).'">'.esc($item['title'] ?: $item['name']).'</a></h2><p>'.esc($item['summary']).'</p><p style="margin-top:16px"><a class="button" href="./product?slug='.rawurlencode($item['slug']).'">'.esc(catalog_label($lang,'view')).'</a> <a class="button ghost" href="./compare/add?product_id='.(int)$item['id'].'">'.esc(catalog_label($lang,'compare')).'</a></p></article>';
        $categories = q("SELECT slug,name FROM product_categories WHERE EXISTS (SELECT 1 FROM product_category_links pcl WHERE pcl.category_id=product_categories.id) ORDER BY name LIMIT 50")->fetchAll();
        $categoryOptions = '<option value="">'.esc(catalog_term($lang, 'all_categories')).'</option>';
        foreach ($categories as $item) $categoryOptions .= '<option value="'.esc($item['slug']).'"'.($category===$item['slug']?' selected':'').'>'.esc($item['name']).'</option>';
        $tags = q("SELECT slug,name FROM product_tags WHERE EXISTS (SELECT 1 FROM product_tag_links ptl WHERE ptl.tag_id=product_tags.id) ORDER BY name LIMIT 80")->fetchAll();
        $tagOptions = '<option value="">'.esc(catalog_term($lang, 'tag')).'</option>';
        foreach ($tags as $item) $tagOptions .= '<option value="'.esc($item['slug']).'"'.($tag===$item['slug']?' selected':'').'>'.esc($item['name']).'</option>';
        $body='<section class="hero"><span class="eyebrow">'.esc(catalog_label($lang,'comparison')).'</span><h1>'.esc(catalog_label($lang,'search_title')).'</h1><p>'.esc(catalog_label($lang,'search_lead')).'</p>'.catalog_currency_switcher($lang).'<form method="get" class="card"><label>'.esc(catalog_label($lang,'search')).'<input name="q" value="'.esc($term).'" placeholder="'.esc(catalog_label($lang,'search_title')).'"></label><label>'.esc(catalog_term($lang,'type')).'<select name="kind"><option value="">'.esc(catalog_label($lang,'all')).'</option><option value="application"'.($kind==='application'?' selected':'').'>'.esc(catalog_label($lang,'software')).'</option><option value="theme"'.($kind==='theme'?' selected':'').'>'.esc(catalog_label($lang,'themes')).'</option><option value="addon"'.($kind==='addon'?' selected':'').'>'.esc(catalog_label($lang,'addons')).'</option></select></label><label>'.esc(catalog_term($lang,'category')).'<select name="category">'.$categoryOptions.'</select></label><label>'.esc(catalog_term($lang,'tag')).'<select name="tag">'.$tagOptions.'</select></label><label>'.esc(catalog_term($lang,'sort')).'<select name="sort"><option value="new"'.($sort==='new'?' selected':'').'>'.esc(catalog_label($lang,'newest')).'</option><option value="popular"'.($sort==='popular'?' selected':'').'>'.esc(catalog_label($lang,'popular')).'</option></select></label><button>'.esc(catalog_label($lang,'search')).'</button><a class="button ghost" href="./compare">'.esc(catalog_label($lang,'compare')).'</a></form></section><section class="grid">'.($cards!==''?$cards:'<article class="card"><h2>'.esc(catalog_label($lang,'no_match')).'</h2><p>'.esc(catalog_label($lang,'try_again')).'</p></article>').'</section>';
        layout(catalog_term($lang,'search_title'),$body,true);
    }
    if ($path !== '/product') return;
    $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
    $id = (int)($_GET['id'] ?? 0);
    $lang = lc_public_locale();
    $product = $id > 0 ? one("SELECT * FROM products WHERE id=? AND status='published'", [$id]) : one("SELECT p.* FROM products p LEFT JOIN product_translations localized_slug ON localized_slug.product_id=p.id AND localized_slug.locale=? AND localized_slug.status='published' WHERE p.status='published' AND (p.slug=? OR localized_slug.slug=?) LIMIT 1", [$lang,$slug,$slug]);
    if (!$product) { http_response_code(404); layout(catalog_label($lang,'product_missing'),'<div class="card"><h1>'.esc(catalog_label($lang,'product_missing')).'</h1><p>'.esc(catalog_label($lang,'not_available')).'</p></div>',true); exit; }
    if (!$product && $slug !== '') $product = one("SELECT p.* FROM products p JOIN product_translations localized_slug ON localized_slug.product_id=p.id AND localized_slug.locale=? AND localized_slug.status='published' WHERE p.status='published' AND localized_slug.slug=? LIMIT 1", [$lang,$slug]);
    $translation = one("SELECT * FROM product_translations WHERE product_id=? AND locale=? AND status='published'", [$product['id'],$lang]);
    if (!$translation) { layout(catalog_label($lang,'preparing'),'<div class="card"><h1>'.esc(catalog_label($lang,'preparing')).'</h1><p>'.esc(catalog_label($lang,'translation_missing')).'</p></div>',true); exit; }
    q('INSERT INTO product_metrics(product_id,views,updated_at) VALUES(?,1,CURRENT_TIMESTAMP) ON CONFLICT(product_id) DO UPDATE SET views=views+1,updated_at=CURRENT_TIMESTAMP', [$product['id']]);
     $displayCurrency = catalog_display_currency($lang);
     $prices = q('SELECT currency,amount_minor,compare_minor,tax_mode FROM product_prices WHERE product_id=? AND active=1 ORDER BY currency', [$product['id']])->fetchAll();
     $displayPrice = null;
     foreach ($prices as $price) {
         if (catalog_currency_key((string)$price['currency']) === catalog_currency_key($displayCurrency)) { $displayPrice = $price; $displayPrice['currency'] = $displayCurrency; break; }
     }
     if (!$displayPrice && $prices) {
         $displayPrice = $prices[0];
         $displayPrice['amount_minor'] = catalog_display_amount((int)$displayPrice['amount_minor'], (string)$displayPrice['currency'], $displayCurrency);
         $displayPrice['compare_minor'] = catalog_display_amount((int)$displayPrice['compare_minor'], (string)$displayPrice['currency'], $displayCurrency);
         $displayPrice['currency'] = $displayCurrency;
     }
    $assets = q("SELECT * FROM product_assets WHERE product_id=? AND status='published' ORDER BY sort_order,id", [$product['id']])->fetchAll();
    $related = q("SELECT DISTINCT p.slug,p.name,p.kind,t.title,t.summary FROM product_category_links current_link JOIN product_category_links related_link ON related_link.category_id=current_link.category_id AND related_link.product_id<>current_link.product_id JOIN products p ON p.id=related_link.product_id AND p.status='published' JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE current_link.product_id=? ORDER BY p.updated_at DESC,p.id DESC LIMIT 4", [$lang,$product['id']])->fetchAll();
    $bundle = one("SELECT id,code,discount_percent FROM product_bundles WHERE product_id=? AND status='published' LIMIT 1", [$product['id']]);
    $bundleItems = $bundle ? q("SELECT p.slug,p.kind,t.title,t.summary FROM product_bundle_items i JOIN products p ON p.id=i.product_id AND p.status='published' JOIN product_translations t ON t.product_id=p.id AND t.locale=? AND t.status='published' WHERE i.bundle_id=? ORDER BY i.sort_order,i.product_id", [$lang,$bundle['id']])->fetchAll() : [];
    $bundleLabels = ['tr'=>['title'=>'Bu paket neleri içeriyor?','discount'=>'Paket indirimi'], 'en'=>['title'=>'What is included in this bundle?','discount'=>'Bundle discount'], 'de'=>['title'=>'Was ist in diesem Paket enthalten?','discount'=>'Paketrabatt'], 'fr'=>['title'=>'Que contient ce pack ?','discount'=>'Réduction du pack'], 'es'=>['title'=>'¿Qué incluye este paquete?','discount'=>'Descuento del paquete'], 'it'=>['title'=>'Cosa include questo pacchetto?','discount'=>'Sconto del pacchetto'], 'pt'=>['title'=>'O que inclui este pacote?','discount'=>'Desconto do pacote'], 'ru'=>['title'=>'Что входит в этот пакет?','discount'=>'Скидка на пакет'], 'ar'=>['title'=>'ماذا تتضمن هذه الحزمة؟','discount'=>'خصم الحزمة'], 'fa'=>['title'=>'این بسته شامل چیست؟','discount'=>'تخفیف بسته'], 'nl'=>['title'=>'Wat zit er in dit pakket?','discount'=>'Pakketkorting'], 'zh'=>['title'=>'此套餐包含什么？','discount'=>'套餐折扣'], 'ja'=>['title'=>'このパッケージに含まれるもの','discount'=>'パッケージ割引'], 'ko'=>['title'=>'이 번들에는 무엇이 포함되나요?','discount'=>'번들 할인']][$lang] ?? ['title'=>'What is included in this bundle?','discount'=>'Bundle discount'];
    $bundleHtml = '';
    if ($bundle && count($bundleItems) >= 2) {
        $bundleRows = ''; foreach ($bundleItems as $item) $bundleRows .= '<li><a href="./product?slug='.rawurlencode((string)$item['slug']).'">'.esc((string)($item['title'] ?: $item['slug'])).'</a><small> · '.esc(strtoupper((string)$item['kind'])).'</small><br><span>'.esc((string)$item['summary']).'</span></li>';
        $bundleHtml = '<section class="card"><h2>'.esc($bundleLabels['title']).'</h2><ul>'.$bundleRows.'</ul><p class="muted">'.esc($bundleLabels['discount']).': '.(int)$bundle['discount_percent'].'%</p></section>';
    }
    $priceHtml = '';
     if ($displayPrice) $priceHtml = '<span class="product-price">'.esc($displayCurrency).' '.esc(number_format(((int)$displayPrice['amount_minor'])/100,2)).'</span>';
     $plans = q("SELECT id,code,name,license_type,billing_period,domain_limit,support_days,update_days,currency,amount_minor FROM license_plans WHERE product_id=? AND status='published' ORDER BY amount_minor,id", [$product['id']])->fetchAll();
    $planHtml = '';
    foreach ($plans as $plan) {
        $plan['display_name'] = function_exists('lc_plan_name') ? lc_plan_name($plan, $lang) : (string)$plan['name'];
         $planAmount = catalog_display_amount((int)$plan['amount_minor'], (string)$plan['currency'], $displayCurrency);
         $planDescription = function_exists('lc_plan_description') ? lc_plan_description($plan, $lang) : '';
         $planHtml .= '<article class="card"><h3>'.esc($plan['display_name']).'</h3><p class="product-price">'.esc($displayCurrency).' '.esc(number_format($planAmount/100,2)).'</p>'.($planDescription !== '' ? '<p>'.esc($planDescription).'</p>' : '').'<p>'.esc(catalog_plan_term($lang,'license_type',(string)$plan['license_type'])).' · '.esc(catalog_plan_term($lang,'billing_period',(string)$plan['billing_period'])).' · '.(int)$plan['domain_limit'].' '.esc(catalog_plan_term($lang,'domains')).'</p><a class="button" href="./checkout?product='.rawurlencode((string)$product['slug']).'&plan='.rawurlencode((string)$plan['code']).'&currency='.rawurlencode($displayCurrency).'">'.esc(catalog_label($lang,'choose_plan')).'</a></article>';
    }
    $plansHtml = $planHtml !== '' ? '<section><h2>'.esc(catalog_label($lang,'plans')).'</h2><div class="grid">'.$planHtml.'</div></section>' : '';
    $assetHtml = '';
    foreach ($assets as $asset) {
        $url = $asset['url'] !== '' ? $asset['url'] : $asset['storage_path'];
        if ($url === '') continue;
        $label = esc($asset['label'] !== '' ? $asset['label'] : ucfirst((string)$asset['kind']));
        if ((string)$asset['kind'] === 'video') {
            $assetHtml .= '<li><video controls preload="metadata" style="display:block;width:100%;max-width:900px;max-height:520px;object-fit:cover;border-radius:16px;background:#08283a" src="'.esc($url).'" aria-label="'.$label.'"></video><br><a href="'.esc($url).'">'.$label.' ↗</a></li>';
        } elseif (in_array((string)$asset['kind'], ['screenshot','og_image','icon'], true)) {
            $assetHtml .= '<li><figure style="margin:0"><img loading="lazy" decoding="async" src="'.esc($url).'" alt="'.$label.'" style="display:block;width:100%;max-width:900px;max-height:520px;object-fit:cover;border-radius:16px;border:1px solid #dfe6e8;background:#f5f6f3"><figcaption><a href="'.esc($url).'">'.$label.' ↗</a></figcaption></figure></li>';
        } else {
            $assetHtml .= '<li><a href="'.esc($url).'">'.$label.' ↗</a></li>';
        }
    }
    $releaseRequirements = $product['kind'] === 'theme'
        ? one("SELECT requirements_json FROM theme_packages WHERE product_id=? AND status='published' ORDER BY created_at DESC,id DESC LIMIT 1", [$product['id']])
        : one("SELECT manifest_json FROM application_releases WHERE product_id=? AND status='published' ORDER BY created_at DESC,id DESC LIMIT 1", [$product['id']]);
    $releaseReady = false;
    $releaseRows = $product['kind'] === 'theme'
        ? q("SELECT storage_path,sha256,signature,theme_id,version,package_name,changelog,created_at FROM theme_packages WHERE product_id=? AND status='published' ORDER BY created_at DESC,id DESC", [$product['id']])->fetchAll()
        : q("SELECT package_path,sha256,signature,manifest_json,version,package_name,changelog,created_at FROM application_releases WHERE product_id=? AND channel='stable' AND status='published' ORDER BY created_at DESC,id DESC", [$product['id']])->fetchAll();
    $verifiedReleases = [];
    foreach ($releaseRows as $releaseRow) {
        $packagePath = (string)($releaseRow['storage_path'] ?? $releaseRow['package_path'] ?? '');
        $hash = strtolower((string)($releaseRow['sha256'] ?? ''));
        if (is_file($packagePath) && preg_match('/^[a-f0-9]{64}$/', $hash) && hash_equals($hash, strtolower((string)hash_file('sha256', $packagePath))) && release_package_signature_valid($releaseRow, $product['kind'] === 'theme' ? 'theme' : 'application')) { $releaseReady = true; $verifiedReleases[] = $releaseRow; }
    }
    $releaseRowsHtml = '';
    foreach ($verifiedReleases as $release) $releaseRowsHtml .= '<article class="card"><strong>'.esc(catalog_term($lang,'version')).' '.esc((string)$release['version']).'</strong><small style="display:block">'.esc(catalog_term($lang,'published')).': '.esc((string)$release['created_at']).'</small><p>'.nl2br(esc((string)($release['changelog'] ?? ''))).'</p></article>';
    $releaseHtml = '<section><h2>'.esc(catalog_term($lang,'releases')).'</h2><div class="grid">'.($releaseRowsHtml !== '' ? $releaseRowsHtml : '<p class="muted">'.esc(catalog_term($lang,'no_releases')).'</p>').'</div></section>';
    $requirements = $releaseRequirements ? json_decode((string)($releaseRequirements['requirements_json'] ?? $releaseRequirements['manifest_json'] ?? ''), true) : null;
    $compatibilityItems = [];
    if (is_array($requirements)) {
        foreach ([
            'min_php'=>['php','string'], 'php'=>['php','string'], 'extensions'=>['extensions','list'],
            'database'=>['database','string'], 'cms_version'=>['cms','string'], 'min_migration_version'=>['migration','string'],
        ] as $field => [$label,$type]) {
            if (!array_key_exists($field, $requirements) || $requirements[$field] === '' || $requirements[$field] === null) continue;
            $value = $requirements[$field];
            if ($type === 'list' && is_array($value)) $value = implode(', ', array_map(static fn(mixed $item): string => (string)$item, $value));
            if (is_scalar($value) && trim((string)$value) !== '') $compatibilityItems[] = '<li><strong>'.esc(catalog_term($lang,$label)).':</strong> '.esc((string)$value).'</li>';
        }
    }
    $compatibilityHtml = $compatibilityItems !== '' && $compatibilityItems !== [] ? '<section><h2>'.esc(catalog_term($lang,'compatibility')).'</h2><ul>'.implode('', $compatibilityItems).'</ul></section>' : '';
    $reviews = q("SELECT rating,title,body,created_at FROM product_reviews WHERE product_id=? AND status='published' ORDER BY created_at DESC LIMIT 20", [$product['id']])->fetchAll();
    $reviewHtml = ''; foreach ($reviews as $review) $reviewHtml .= '<article class="card"><strong>'.str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating']).' · '.esc($review['title']).'</strong><p>'.nl2br(esc($review['body'])).'</p></article>';
    $favoriteHtml = !empty($_SESSION['buyer']) ? '<form method="post" action="./account/favorite" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="product_id" value="'.(int)$product['id'].'"><button class="button ghost">'.esc(catalog_label($lang,'save')).'</button></form>' : '<a class="button ghost" href="./account/login">'.esc(catalog_label($lang,'signin_save')).'</a>';
    $compareHtml = '<a class="button ghost" href="./compare/add?product_id='.(int)$product['id'].'">'.esc(catalog_label($lang,'compare')).'</a>';
    $schemaType = $product['kind'] === 'application' ? 'SoftwareApplication' : 'Product';
    $schema = ['@context'=>'https://schema.org','@type'=>$schemaType,'name'=>$translation['title'] ?: $product['name'],'description'=>$translation['summary'] ?: $translation['description'],'url'=>rtrim(lc_env('APP_URL','https://product.tofidesk.com'),'/').'/product?slug='.rawurlencode($product['slug']),'inLanguage'=>$lang];
    $customSchema = json_decode((string)($translation['schema_json'] ?? ''), true);
    if (is_array($customSchema) && isset($customSchema['@type'])) $schema = array_merge($schema, $customSchema);
    $schemaGraph = [$schema];
    foreach (['faq_schema_json','breadcrumb_schema_json'] as $schemaField) {
        $extraSchema = json_decode((string)($translation[$schemaField] ?? ''), true);
        if (is_array($extraSchema) && isset($extraSchema['@type'])) $schemaGraph[] = $extraSchema;
    }
    if (!empty($translation['canonical_url']) && filter_var($translation['canonical_url'], FILTER_VALIDATE_URL)) $schema['url'] = $translation['canonical_url'];
    if (!empty($translation['og_image']) && filter_var($translation['og_image'], FILTER_VALIDATE_URL)) $schema['image'] = $translation['og_image'];
    $offerItems = [];
    foreach ($plans as $plan) {
        $offerAmount = catalog_display_amount((int)$plan['amount_minor'], (string)$plan['currency'], $displayCurrency);
        $offerItems[] = ['@type'=>'Offer','name'=>function_exists('lc_plan_name') ? lc_plan_name($plan, $lang) : (string)$plan['name'],'price'=>(string)($offerAmount / 100),'priceCurrency'=>$displayCurrency,'availability'=>'https://schema.org/InStock','url'=>$schema['url'].'&plan='.rawurlencode((string)$plan['code'])];
    }
    if ($offerItems) $schema['offers'] = count($offerItems) === 1 ? $offerItems[0] : $offerItems;
    elseif ($displayPrice) $schema['offers'] = ['@type'=>'Offer','price'=>(string)(((int)$displayPrice['amount_minor'])/100),'priceCurrency'=>$displayCurrency,'availability'=>'https://schema.org/InStock','url'=>$schema['url']];
    $schemaGraph[0] = $schema;
    if (count($schemaGraph) > 1) $schema = ['@context'=>'https://schema.org','@graph'=>$schemaGraph];
    $reviewForm = !empty($_SESSION['buyer']) ? '<form class="card" method="post" action="./product-review"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="product_id" value="'.(int)$product['id'].'"><h2>'.esc(catalog_label($lang,'write_review')).'</h2><label>'.esc(catalog_label($lang,'rating')).'<select name="rating"><option value="5">5 / 5</option><option value="4">4 / 5</option><option value="3">3 / 5</option><option value="2">2 / 5</option><option value="1">1 / 5</option></select></label><label>'.esc(catalog_label($lang,'title')).'<input name="title" maxlength="180" required></label><label>'.esc(catalog_label($lang,'review')).'<textarea name="body" maxlength="5000" required></textarea></label><button>'.esc(catalog_label($lang,'submit_review')).'</button></form>' : '';
    $relatedHtml = ''; foreach ($related as $item) $relatedHtml .= '<article class="card"><span class="eyebrow">'.esc(strtoupper($item['kind'])).'</span><h3><a href="./product?slug='.rawurlencode($item['slug']).'">'.esc($item['title'] ?: $item['name']).'</a></h3><p>'.esc($item['summary']).'</p></article>';
    $buyAction = $releaseReady ? '<a class="button" href="./checkout?product='.rawurlencode($product['slug']).'">'.esc(catalog_label($lang,'buy')).'</a>' : '<span class="button ghost" aria-disabled="true">'.esc(catalog_term($lang,'preview_only')).'</span>';
    $body = '<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script><article class="card product-detail"><div class="product-toolbar">'.catalog_currency_switcher($lang).'</div><span class="eyebrow">'.esc(strtoupper((string)$product['kind'])).'</span><h1>'.esc($translation['title'] ?: $product['name']).'</h1><p class="product-summary">'.esc($translation['summary']).'</p><div class="product-prices">'.$priceHtml.'</div><div class="actions">'.$buyAction.$favoriteHtml.$compareHtml.'<a class="button ghost" href="./shop">'.esc(catalog_label($lang,'back')).'</a></div><section><h2>'.esc(catalog_label($lang,'about')).'</h2><p>'.nl2br(esc($translation['description'])).'</p></section>'.$bundleHtml.($compatibilityHtml!==''?$compatibilityHtml:'').($assetHtml!==''?'<section><h2>'.esc(catalog_label($lang,'assets')).'</h2><ul>'.$assetHtml.'</ul></section>':'').'</article>'.$releaseHtml.$plansHtml.'<section><h2>'.esc(catalog_label($lang,'reviews')).'</h2>'.($reviewHtml ?: '<p class="muted">'.esc(catalog_label($lang,'no_reviews')).'</p>').'</section>'.$reviewForm.($relatedHtml!==''?'<section><h2>'.esc(catalog_label($lang,'related')).'</h2><div class="grid">'.$relatedHtml.'</div></section>':'');
    layout($translation['seo_title'] ?: $translation['title'] ?: $product['name'], $body, true);
}
