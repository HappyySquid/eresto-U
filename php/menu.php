<?php

// chargement des bibliothèques de fonctions
require_once('bibli_erestou.php');
require_once('bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

// affichage de l'entête
affEntete('Menus et repas');
// affichage de la barre de navigation
affNav();

// contenu de la page 
affContenuL();

// affichage du pied de page
affPiedDePage();

// fin du script --> envoi de la page 
ob_end_flush();


//_______________________________________________________________
/**
 * Vérifie la validité des paramètres reçus dans l'URL, renvoie la date affichée ou l'erreur détectée
 *
 * La date affichée est initialisée avec la date courante ou actuelle.
 * Les éventuels paramètres jour, mois, annee, reçus dans l'URL, permettent respectivement de modifier le jour, le mois, et l'année de la date affichée.
 *
 * @return int|string      string en cas d'erreur, int représentant la date affichée au format AAAAMMJJ sinon
 */
function dateConsulteeL() : int|string {
    if (!parametresControle('GET', [], ['jour', 'mois', 'annee'])){
        return 'Nom de paramètre invalide détecté dans l\'URL.';
    }

    // date d'aujourd'hui
    list($jour, $mois, $annee) = getJourMoisAnneeFromDate(DATE_AUJOURDHUI);

    // vérification si les valeurs des paramètres reçus sont des chaînes numériques entières
    foreach($_GET as $cle => $val){
        if (! estEntier($val)){
            return 'Valeur de paramètre non entière détectée dans l\'URL.';
        }
        // modification du jour, du mois ou de l'année de la date affichée
        $$cle = (int)$val;
    }

    if ($annee < 1000 || $annee > 9999){
        return 'La valeur de l\'année n\'est pas sur 4 chiffres.';
    }
    if (!checkdate($mois, $jour, $annee)) {
        return "La date demandée \"$jour/$mois/$annee\" n'existe pas.";
    }
    if ($annee < ANNEE_MIN){
        return 'L\'année doit être supérieure ou égale à '.ANNEE_MIN.'.';
    }
    if ($annee > ANNEE_MAX){
        return 'L\'année doit être inférieure ou égale à '.ANNEE_MAX.'.';
    }
    return $annee*10000 + $mois*100 + $jour;
}
//_______________________________________________________________
/**
 * Génération de la navigation entre les dates
 *
 * @param  int     $date   date affichée
 *
 * @return void
 */
function affNavigationDateL(int $date): void{
    list($jour, $mois, $annee) = getJourMoisAnneeFromDate($date);

    // on détermine le jour précédent (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj--;
        $dateVeille = getdate(mktime(12, 0, 0, $mois, $jour+$jj, $annee));
    } while ($dateVeille['wday'] == 0 || $dateVeille['wday'] == 6);
    // on détermine le jour suivant (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj++;
        $dateDemain = getdate(mktime(12, 0, 0, $mois, $jour+$jj, $annee));
    } while ($dateDemain['wday'] == 0 || $dateDemain['wday'] == 6);

    $dateJour = getdate(mktime(12, 0, 0, $mois, $jour, $annee));
    $jourSemaine = array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');

    // affichage de la navigation pour choisir le jour affiché
    echo '<h2>',
            $jourSemaine[$dateJour['wday']], ' ',
            $jour, ' ',
            getTableauMois()[$dateJour['mon']-1], ' ',
            $annee,
        '</h2>',

        // on utilise un formulaire qui renvoie sur la page courante avec une méthode GET pour faire apparaître les 3 paramètres sur l'URL
        '<form id="navDate" action="menu.php" method="GET">',
            '<a href="menu.php?jour=', $dateVeille['mday'], '&amp;mois=', $dateVeille['mon'], '&amp;annee=',  $dateVeille['year'], '">Jour précédent</a>',
            '<a href="menu.php?jour=', $dateDemain['mday'], '&amp;mois=', $dateDemain['mon'], '&amp;annee=', $dateDemain['year'], '">Jour suivant</a>',
            'Date : ';

    affListeNombre('jour', 1, 31, 1, $jour);
    affListeMois('mois', $mois);
    affListeNombre('annee', ANNEE_MIN, ANNEE_MAX, 1, $annee);

    echo    '<input type="submit" value="Consulter">',
        '</form>';
        // le bouton submit n'a pas d'attribut name. Par conséquent, il n'y a pas d'élément correspondant transmis dans l'URL lors de la soumission
        // du formulaire. Ainsi, l'URL de la page a toujours la même forme (http://..../php/menu.php?jour=7&mois=3&annee=2023) quel que soit le moyen
        // de navigation utilisé (formulaire avec bouton 'Consulter', ou lien 'précédent' ou 'suivant')
}

//_______________________________________________________________
/**
 * Récupération du menu de la date affichée
 *
 * @param int       $date           date affichée
 * @param array     $menu           menu de la date affichée (paramètre de sortie)
 *
 * @return bool                     true si le restoU est ouvert, false sinon
 */
function bdMenuL(int $date, array &$menu) : bool {

    // ouverture de la connexion à la base de données
    $bd = bdConnect();

    // Récupération des plats qui sont proposés pour le menu (boissons incluses, divers exclus)
    $sql = "SELECT plID, plNom, plCategorie, plCalories, plCarbone
            FROM plat LEFT JOIN menu ON (plID=mePlat AND meDate=$date)
            WHERE mePlat IS NOT NULL OR plCategorie = 'boisson'";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    // Quand le resto U est fermé, la requête précédente renvoie tous les enregistrements de la table Plat de
    // catégorie boisson : il y en a NB_CAT_BOISSON
    if (mysqli_num_rows($res) <= NB_CAT_BOISSON) {
        // libération des ressources
        mysqli_free_result($res);
        // fermeture de la connexion au serveur de base de  données
        mysqli_close($bd);
        return false; // ==> fin de la fonction bdMenuL()
    }


    // tableau associatif contenant les constituants du menu : un élément par section
    $menu = array(  'entrees'           => array(),
                    'plats'             => array(),
                    'accompagnements'   => array(),
                    'desserts'          => array(),
                    'boissons'          => array()
                );

    // parcours des ressources :
    while ($tab = mysqli_fetch_assoc($res)) {
        switch ($tab['plCategorie']) {
            case 'entree':
                $menu['entrees'][] = $tab;
                break;
            case 'viande':
            case 'poisson':
                $menu['plats'][] = $tab;
                break;
            case 'accompagnement':
                $menu['accompagnements'][] = $tab;
                break;
            case 'dessert':
            case 'fromage':
                $menu['desserts'][] = $tab;
                break;
            default:
                $menu['boissons'][] = $tab;
        }
    }
    // libération des ressources
    mysqli_free_result($res);
    // fermeture de la connexion au serveur de base de  données
    mysqli_close($bd);
    return true;
}

//_______________________________________________________________
/**
 * Affichage d'un des constituants du menu.
 *
 * @param  array       $p      tableau associatif contenant les informations du plat en cours d'affichage
 * @param  string      $catAff catégorie d'affichage du plat
 *
 * @return void
 */
function affPlatL(array $p, string $catAff, string $disa="disabled", string $check=""): void {
    if ($catAff != 'accompagnements'){ //radio bonton
        $name = "rad$catAff";
        $id = "{$name}{$p['plID']}";
        $type = 'radio';
    }
    else{ //checkbox
        $id = $name = "cb{$p['plID']}";
        $type = 'checkbox';
    }

    // protection des sorties contre les attaques XSS
    $p['plNom'] = htmlProtegerSorties($p['plNom']);

    echo    '<input id="', $id, '" name="', $name, '" type="', $type, '" value="', $p['plID'], '"',$disa,' '.$check.'>',
            '<label for="', $id,'">',
                '<img src="../images/repas/', $p['plID'], '.jpg" alt="', $p['plNom'], '" title="', $p['plNom'], '">',
                $p['plNom'], '<br>', '<span>', $p['plCarbone'],'kg eqCO2 / ', $p['plCalories'], 'kcal</span>',
            '</label>';

}

//_______________________________________________________________
/**
 * Génère le contenu de la page.
 *
 * @return void
 */
function affContenuL(): void {

    $date = dateConsulteeL();
    $bd = bdConnect();
    $id = estAuthentifie() ? htmlProtegerSorties($_SESSION['usID']) : null;
   
    // si dateConsulteeL() renvoie une erreur
    if (is_string($date)){
        echo    '<h4 class="center nomargin">Erreur</h4>',
                '<p>', $date, '</p>',
                (strpos($date, 'URL') !== false) ?
                '<p>Il faut utiliser une URL de la forme :<br>http://..../php/menu.php?jour=7&mois=3&annee=2023</p>':'';
        return; // ==> fin de la fonction affContenuL()
    }
    // si on arrive à ce point de l'exécution, alors la date est valide
    
    // Génération de la navigation entre les dates 
    affNavigationDateL($date);

    // menu du jour
    $menu = [];
    $restoOuvert = bdMenuL($date, $menu);
    if (! $restoOuvert){
        echo '<p>Aucun repas n\'est servi ce jour.</p>';
        return; // ==> fin de la fonction affContenuL()
    }else{

        if(estAuthentifie()){//si connecté

            $today = date("Ymd"); //date du jour
            
            if($date<$today){//date antérieur
                afficherPreviousCommande($menu,$date,$bd,$id);
                //afficher commande precédente si exsite
            }else{
                if($date==$today){

                    //IL FAUT TESTER SI DEJA COMMANDER OU PAS  SI OUI AFFIHCER MENUN PREV
                    //insérer la commande
                    if(testSiDejaCommande($bd,$date,$id)){
                        afficherPreviousCommande($menu,$date,$bd,$id);
                    }else{
                        echo"            <p class=\"notice\">
                        <img src=\"../images/notice.png\" alt=\"notice\" width=\"50\" height=\"48\">
                        Tous les plateaux sont composés avec un verre, un couteau, une fouchette et une petite cuillère.
                    </p>";
                        traitementCommande();
                        afficherCommande($menu);
                    }
                    
                }else{
                    afficherMenuNonConnecté($menu);
                } 
            }


        }else{

            afficherMenuNonConnecté($menu);
            
        }
    }

    mysqli_close($bd);

}

/**
 *   Vérifie si un utilisateur a déjà commandé un accompagnement pour une date donnée
 *
 * @param mysqli $bd - la connexion à la base de données
 * @param string $date - la date de la commande (format 'Y-m-d')
 * @param int $id - l'identifiant de l'utilisateur
 * @return bool - true si l'utilisateur a déjà commandé un accompagnement, false sinon
 */
function testSiDejaCommande($bd,$date,$id):bool {
    $sql="SELECT rePlat FROM repas,plat where rePlat=plID and reDate=".$date." and reUsager=".$id." and plCategorie=\"accompagnement\"";

    $res =  bdSendRequest($bd, $sql);

    $tab = mysqli_fetch_assoc($res);

    if(isset($tab['rePlat'])){
        mysqli_free_result($res);
        return true;
    }else{
        mysqli_free_result($res);
        return false;
    }
}
/**
 * 
 * Affiche le menu pour un utilisateur non connecté
 *
 * @param array $menu - un tableau multidimensionnel contenant les plats à afficher pour chaque section
 * @return void
 */
function afficherMenuNonConnecté($menu) : void {
    // titre h3 des sections à afficher
    $h3 = array('entrees'           => 'Entrée',
    'plats'             => 'Plat', 
    'accompagnements'   => 'Accompagnement(s)',
    'desserts'          => 'Fromage/dessert', 
    'boissons'          => 'Boisson'
    );

    // affichage du menu
    foreach($menu as $key => $value){
        echo '<section class="bcChoix"><h3>', $h3[$key], '</h3>';
        foreach ($value as $p) {
            affPlatL($p, $key);
        }
        echo '</section>';
    }
}

/**
 * 
 * Affiche le menu sur la page d'accueil pour les utilisateurs non connectés
 *
 * @param array $menu - le menu à afficher, un tableau associatif avec les catégories de plats comme clés et un tableau de plats comme valeurs
 * @return void 
 */

function afficherPreviousCommande($menu,$date,$bd,$id){

    
    $sql="SELECT plCategorie,reNbPortions,plID FROM repas, plat WHERE rePlat=plID AND reDate=".$date." and reUsager=".$id."";
    
    $res =  bdSendRequest($bd, $sql);

    $menuCommande = [array(  'entrees'           => array(),
    'plats'             => array(),
    'accompagnements'   => array(),
    'desserts'          => array(),
    'boissons'          => array()
    )];

    // parcours des ressources :
    while ($tab = mysqli_fetch_assoc($res)) {

        switch ($tab['plCategorie']) {
            case 'entree':
            $menuCommande['entrees'][] = $tab;
            break;
            case 'viande':
            case 'poisson':
            $menuCommande['plats'][] = $tab;
            break;
            case 'accompagnement':
            $menuCommande['accompagnements'][] = $tab;
            break;
            case 'dessert':
            case 'fromage':
            $menuCommande['desserts'][] = $tab;
            break;
            default:
            $menuCommande['boissons'][] = $tab;
        }
    }
    $previousId=0;
    // titre h3 des sections à afficher
    $h3 = array('entrees'           => 'Entrée',
    'plats'             => 'Plat', 
    'accompagnements'   => 'Accompagnement(s)',
    'desserts'          => 'Fromage/dessert', 
    'boissons'          => 'Boisson'
    );
    $bool=true;//Booleen qui permet de ne pas afficher les plats deja affiché
   
    // affichage du menu
    foreach($menu as $key => $value){
        echo '<section class="bcChoix"><h3>', $h3[$key], '</h3>';
        foreach ($value as $p) {
            if(!isset($menuCommande[$key])){
                affPlatL($p, $key);
            }else{
                
                foreach ($menuCommande[$key] as $plat) {
    
                    if ($plat['plID'] == $p['plID']) {
                        affPlatL($p, $key,"","checked");
                        break;
                    }else{
                        if($bool){
                            affPlatL($p, $key);
                        }
                        //affPlatL($p, $key);
                    }
                        $bool=false;                                       
                }
            }
            if($key!="accompagnements" ){
                $bool=true;
            }
           
        }
        echo '</section>';
    }
    
    mysqli_free_result($res);

}

/**
 * Affiche le menu et permet à l'utilisateur de passer une commande.
 *
 * @param array $menu Un tableau associatif contenant les différents éléments du menu.
 * @return void
 */

function afficherCommande ($menu) : void {
    // titre h3 des sections à afficher
    $h3 = array('entrees'           => 'Entrée',
    'plats'             => 'Plat', 
    'accompagnements'   => 'Accompagnement(s)',
    'desserts'          => 'Fromage/dessert', 
    'boissons'          => 'Boisson'
    );
    echo"<form method=\"post\" action=\"menu.php\">"; // Début form
    // affichage du menu
    foreach($menu as $key => $value){
        echo '<section class="bcChoix"><h3>', $h3[$key], '</h3>';
        if($h3[$key]=="Entrée" ){
            afficherInputNoPlat("Pas d'entrée","radentreesnull","radentrees");         
        }
        if($h3[$key]=="Plat" ){
            afficherInputNoPlat("Pas de plat","radplatnull","radplats");         
        }
        if($h3[$key]=="Fromage/dessert" ){
            afficherInputNoPlat("Pas de fromage/déssert","raddessertnull","raddesserts");         
        }                     

        foreach ($value as $p) {
            affPlatL($p, $key,$disa="");
        }
        echo '</section>';
    }
    echo "<section class=\"bcChoix\">
                    <h3>Suppléments</h3>
                    <label>
                        <img src=\"../images/repas/38.jpg\" alt=\"Pain\" title=\"Pain\">Pain
                        <input type=\"number\" min=\"0\" max=\"2\" name=\"nbPains\" value=\"0\">
                    </label>
                    <label>
                        <img src=\"../images/repas/39.jpg\" alt=\"Serviette en papier\" title=\"Serviette en papier\">Serviette en papier
                        <input type=\"number\" min=\"1\" max=\"5\" name=\"nbServiettes\" value=\"1\">
                    </label>
                </section>
                <section>
                    <h3>Validation</h3>
                        <p class=\"attention\">
                            <img src=\"../images/attention.png\" alt=\"attention\" width=\"50\" height=\"50\">
                            Attention, une fois la commande réalisée, il n'est pas possible de la modifier.<br>
                            Toute commande non-récupérée sera majorée d'une somme forfaitaire de 10 euros.
                        </p>
                        <p class=\"center\">
                            <input type=\"submit\" name=\"btnCommander\" value=\"Commander\">
                            <input type=\"reset\" name=\"btnAnnuler\" value=\"Annuler\">
                        </p>
                </section>";
        echo"</form>";
    
}

/**
 * Affiche un bouton radio et une étiquette pour un plat sans option sélectionnée.
 *
 * @param string $title Le titre du plat.
 * @param string $id    L'ID de l'élément de formulaire.
 * @param string $name  Le nom de l'élément de formulaire.
 * @return void
 */
function afficherInputNoPlat(string $title,string $id,string $name){
    echo "<input id=\"".$id."\" name=\"".$name."\" type=\"radio\" value=\"aucune\">
    <label for=\"".$id."\">
        <img src=\"../images/repas/0.jpg\" alt=\"".$title."\" title=\"".$title."\">".$title."
    </label>"; 
}
/**
 * Vérifie les données du formulaire de commande et insère une nouvelle commande en base de données si les données sont valides.
 *
 * @return void
 */
function traitementCommande() :void {

    if (isset($_POST['btnCommander'])){
        $erreurs=[];
        $erreurs=testErreurPostCommande($erreurs);
        if (count($erreurs) > 0) { // afficahge de potentiel erreurs
            if (is_array($erreurs)) {
                echo    '<div class="error">Les erreurs suivantes ont été relevées durant le traitement de votre commande :',
                            '<ul>';
                foreach ($erreurs as $e) {
                    echo        '<li>', $e, '</li>';
                }
                echo        '</ul>',
                        '</div>';
            }
        }else{
             //requete sql pour rentrer le repas 
             envoieCommande ($bd);

        }
    }

}

/**
 * Vérifie les erreurs potentielles dans les données POST pour le formulaire de commande
 * @param array $erreurs tableau contenant les erreurs détectées, initialisé à vide
 * @return array le tableau des erreurs éventuellement complété
 */

function testErreurPostCommande(array $erreurs): array {
    $nbAccom=0;
    if(!isset($_POST['radentrees'])){
        $erreurs[] = "Choix d'entrée incorrect.";            
    }
    if(!isset($_POST['radplats'])){
        $erreurs[] = "Choix de plat incorrect."; 
    }
    if(isset($_POST['cb28'])){
        $nbAccom++;
    }
    if(isset($_POST['cb29'])){
        $nbAccom++;
    }
    if(isset($_POST['cb30'])){
        $nbAccom++;
    }
    if(!isset($_POST['raddesserts'])){
        $erreurs[] = "Choix de dessert/fromage incorrect."; 
    }
    if(!isset($_POST['radboissons'])){
        $erreurs[] = "Le choix d'une boisson est obligatoire."; 
    }
    if(!isset($_POST['nbPains'])){
        $erreurs[] = "Nombre de pain incorect.";
    }else{
        if($_POST['nbPains']<0||$_POST['nbPains']>2){
            $erreurs[] = "Vous avez le droit a maximum deux pain";
        }
    }
    if(!isset($_POST['nbServiettes'])){
        $erreurs[] = "Nombre de serviette incorect.";
    }else{
        if($_POST['nbServiettes']<1||$_POST['nbPains']>5){
            $erreurs[] = "Vous avez le droit a maximum cinq serviettes";
        }
    }
    if($nbAccom==2 && $_POST['radplats']!="radentreesnull"){
        $portion=0.5;
    }  
    
    return $erreurs;
}
/**
 * Calcule le nombre de portions d'accompagnements en fonction des choix de l'utilisateur.
 *
 * @return int Le nombre de portions d'accompagnements.
 */

function nbPortionAccom ():int{
    $portion=1;
    $nbAccom=0;
    if(isset($_POST['cb28'])){
        $nbAccom++;
    }
    if(isset($_POST['cb29'])){
        $nbAccom++;
    }
    if(isset($_POST['cb30'])){
        $nbAccom++;
    }
    if($_POST['radplats']!="radentreesnull"){
        if($nbAccom==1){
            $portion=1;
        }
        if($nbAccom==2){
            $portion=0.5;
        }
        if($nbAccom==3){
            $portion=0.333;
        }
    }else{
        if($nbAccom==1){
            $portion=1.5;
        }
        if($nbAccom==2){
            $portion=0.75;
        }
        if($nbAccom==3){
            $portion=0.5;
        }
    }

    return $portion;

}
function envoieCommande ($bd):void{

}