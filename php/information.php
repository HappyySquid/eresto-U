<?php


// chargement des bibliothèques de fonctions
require_once('bibli_erestou.php');
require_once('bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

// génération de la page
affEntete('Information');
affNav();

if(estAuthentifie()){ 
    echo "<section><h3>Statistique personnelle</h3>";
    afficherStat();
    echo"</section>";
    modifierInfo();
}else{
    echo"<p>Vous n'êtes pas connecté.</p>";
}
affPiedDePage();

// facultatif car fait automatiquement par PHP
ob_end_flush();

/**
 * Affiche les statistiques de l'utilisateur connecté 
 *
 * @return void
 */
function afficherStat(): void{
    $bd = bdConnect();
    $id = estAuthentifie() ? htmlProtegerSorties($_SESSION['usID']) : null;
    $nbRepas=StatNbRepas($id,$bd);
    echo("<ul>");
    if ($nbRepas == 0) {
        echo "<li>Utilisateur sans repas.</li>";
      } else {
        echo "<li>Utilisateur a " . $nbRepas . " repas.</li>";
        $nbRepasCom = statRepasCom($id, $bd);
        if ($nbRepasCom == 0) {
          echo "<li>Utilisateur sans repas commenté.</li>";
        } else {
          $tabRepasCom = statRepasCom($id, $bd);
          echo "<li>Utilisateur a " . $tabRepasCom['nbRepasCom'] . " repas commentés.</li>";
          echo "<li>Utilisateur avec ";
          echo number_format( $tabRepasCom['moyenneNbRepasCom']  , 2, '.', ' '); 
          echo " de moyenne sur ses repas commentés.</li>";
          $pourcentageRepas = ($tabRepasCom['nbRepasCom'] / $nbRepas) * 100;
          echo "<li>L'utilisateur a commenté " . $pourcentageRepas . " % des repas pris.</li>";
          $tabMoyenneCalCar = statMoyenneCalorieCarbone($id, $bd);
          echo "<li>L'utilisateur consomme en moyenne ";
          echo number_format($tabMoyenneCalCar['MoyenneApportCalorique'] , 2, '.', ' '); 
          echo " calorie par repas.</li>";
          echo "<li>L'utilisateur a une empreinte carbone moyenne de " ; 
          echo number_format($tabMoyenneCalCar['MoyenneEmpreinteCarbone'], 2, '.', ' ');
          echo" par repas.</li>";
        }
    }
    echo("</ul>");
    //fermeture de la connexion à la base de données
    mysqli_close($bd);
}

/**
 * Récupère la moyenne d'apport calorique et d'empreinte carbone par repas pour un utilisateur donné.
 *
 * @param int $id Identifiant de l'utilisateur.
 * @param mysqli $bd Objet de connexion à la base de données.
 * @return array Tableau associatif contenant la moyenne d'apport calorique et d'empreinte carbone par repas.
 */

function statMoyenneCalorieCarbone($id,$bd): array {

    $sql="SELECT AVG(MoyenneCalories) as MoyenneApportCalorique, AVG(MoyenneCarbone) as MoyenneEmpreinteCarbone 
    FROM (
      SELECT reDate, AVG(plCalories*reNbPortions) as MoyenneCalories, AVG(plCarbone*reNbPortions) as MoyenneCarbone 
      FROM `repas` join plat on rePlat=plID where reUsager =$id
      GROUP BY reDate
    ) as T;
    ";

    $res =  bdSendRequest($bd, $sql);
    $tabRes;
    while($tab = mysqli_fetch_assoc($res)){
        $tabRes['MoyenneApportCalorique']=$tab['MoyenneApportCalorique'];
        $tabRes['MoyenneEmpreinteCarbone']=$tab['MoyenneEmpreinteCarbone'];
    }

    mysqli_free_result($res);

    return $tabRes;
}
/**
 * Calcule le nombre de repas commentés et la moyenne des notes de l'utilisateur donné.
 *
 * @param int $id Identifiant de l'utilisateur.
 * @param mysqli $bd Connexion à la base de données.
 *
 * @return array Un tableau associatif contenant les informations suivantes :
 *      - 'nbRepasCom' : le nombre de repas commentés par l'utilisateur.
 *      - 'moyenneNbRepasCom' : la moyenne des notes données par l'utilisateur aux repas qu'il a commentés.
 */
function statRepasCom($id,$bd): array {
    
    $sql="SELECT COUNT(DISTINCT coDateRepas) as nbRepasCom ,avg( coNote) as moyenneNbRepasCom FROM commentaire WHERE coUsager=$id";
   
    $res =  bdSendRequest($bd, $sql);
    $tabRes;
    while($tab = mysqli_fetch_assoc($res)){
        $tabRes['nbRepasCom']=$tab['nbRepasCom'];
        $tabRes['moyenneNbRepasCom']=$tab['moyenneNbRepasCom'];
    }
    mysqli_free_result($res);

    return $tabRes;
}
/**
 * Calcule le nombre de repas pris par un utilisateur identifié par son ID.
 * 
 * @param int $id L'ID de l'utilisateur.
 * @param mysqli $bd La connexion à la base de données.
 * @return int Le nombre de repas pris par l'utilisateur.
 */

function statNbRepas($id,$bd): int {

    $sql="SELECT COUNT(DISTINCT reDate) as nbRepas FROM repas JOIN usager ON reUsager=usID WHERE usID=$id";

    $res =  bdSendRequest($bd, $sql);

    $tab = mysqli_fetch_assoc($res);

    $nbRepas=$tab['nbRepas'];

    mysqli_free_result($res);

    return $nbRepas;
}
/**
 * Affiche un formulaire permettant de modifier les informations personnelles de l'utilisateur.
 *
 * @param string $oldEmail L'ancienne adresse email de l'utilisateur.
 * @param string $oldPrenom Le prénom de l'utilisateur.
 * @param string $oldNom Le nom de l'utilisateur.
 * @return void
 */
function formChangeInfoPerso($oldEmail,$oldPrenom,$oldNom) : void {

    echo
        '<form method="post" action="information.php">',
            '<table>';
    affLigneInput(  'NewEmail :', array('type' => 'text', 'name' => 'newEmail', 'value' => '',
                'placeholder' => ''.$oldEmail.'', '', 'required' => null));
    affLigneInput(  'NewPrenom :', array('type' => 'text', 'name' => 'newPrenom', 'value' => '',
                'placeholder' => ''.$oldPrenom.'', '', 'required' => null));
    affLigneInput(  'NewNom :', array('type' => 'text', 'name' => 'newNom', 'value' => '',
                'placeholder' => ''.$oldNom.'', '', 'required' => null));          
                
    echo                    '<tr>',
                    '<td colspan="2">',
                        '<input type="submit" name="btnChgInfoPerso" value="Modifier">',
                        '<input type="reset" value="Annuler">',
                    '</td>',
                '</tr>',
            '</table>',
        '</form>',
    '</section>';
    
}

/**
 * Récupère les informations d'un usager dans la base de données.
 * 
 * @param int $id L'identifiant de l'usager.
 * @param mysqli $bd La connexion à la base de données.
 * 
 * @return array Un tableau associatif contenant les informations de l'usager (prénom, nom, email, login et mot de passe).
 */
function recupInfo($id,$bd) : array {
    $sql="SELECT usPrenom,usNom,usMail,usLogin,usPasse FROM usager WHERE usID=$id";

    $res =  bdSendRequest($bd, $sql);

    $tabRes;
    while($tab = mysqli_fetch_assoc($res)){
        $tabRes['usPrenom']=$tab['usPrenom'];
        $tabRes['usNom']=$tab['usNom'];
        $tabRes['usMail']=$tab['usMail'];
        $tabRes['usLogin']=$tab['usLogin'];
        $tabRes['usPasse']=$tab['usPasse'];
    }

    mysqli_free_result($res);

    return $tabRes;

}

/**
 * Traitement du formulaire de modification d'informations personnelles.
 * Vérifie les données saisies et les enregistre en base de données si elles sont valides.
 * Affiche un message de confirmation ou les erreurs éventuelles.
 *
 * @param int $id L'identifiant de l'utilisateur dont on veut modifier les informations.
 * @param mysqli $bd La connexion à la base de données.
 *
 * @return void
 */
function traitementChangeInfoPerso($id,$bd) : void {
    if (isset($_POST['btnChgInfoPerso'])){
        $erreurs=[];
        $newPrenom = $_POST['newPrenom'] = trim($_POST['newPrenom']);
        $expRegNomPrenom = '/^[[:alpha:]]([\' -]?[[:alpha:]]+)*$/u';
        verifierTexte($newPrenom, 'Le prénom', $erreurs, LMAX_PRENOM, $expRegNomPrenom);
        $newNom = $_POST['newNom'] = trim($_POST['newNom']);
        verifierTexte($newNom, 'Le nom', $erreurs, LMAX_NOM, $expRegNomPrenom);
        $newEmail = $_POST['newEmail'] = trim($_POST['newEmail']);
        verifierTexte($newEmail, 'L\'adresse email', $erreurs, LMAX_EMAIL);
        

        if (count($erreurs) > 0) {
            if (is_array($erreurs)) {
                echo    '<div class="error">Les erreurs suivantes ont été relevées lors de votre inscription :',
                            '<ul>';
                foreach ($erreurs as $e) {
                    echo        '<li>', $e, '</li>';
                }
                echo        '</ul>',
                        '</div>';
            }
        }else{
            $sql="UPDATE usager SET usPrenom= \"".$newPrenom."\" ,usNom = \"".$newNom."\",usMail = \"".$newEmail."\" WHERE usID=".$id."; ";
            $res =  bdSendRequest($bd, $sql);
            
            echo "<p>Vos information personnelle ont bien été <strong>changé</strong></p>";

        }
    }

}

/**
 * Affiche un formulaire de modification d'informations de connexion.
 * Le formulaire contient les champs pour changer le login et le mot de passe de l'utilisateur.
 * @param string $oldLogin Le login actuel de l'utilisateur.
 * @return void
 */

function formChangeInfoConnexion($oldLogin) : void {

    echo
        '<form method="post" action="information.php">',
            '<table>';
    affLigneInput(  'NewLogin :', array('type' => 'text', 'name' => 'newLogin', 'value' => '',
                'placeholder' => ''.$oldLogin.'', '', 'required' => null));
    affLigneInput(  'oldMdp:', array('type' => 'text', 'name' => 'oldPasse', 'value' => '',
                'placeholder' => '' ,'', 'required' => null));
    affLigneInput(  'NewMdp:', array('type' => 'text', 'name' => 'newPasse1', 'value' => '',
                'placeholder' => '' ,'', 'required' => null));
    affLigneInput(  'Répéter NewMdp :', array('type' => 'text', 'name' => 'newPasse2', 'value' => '',
                'placeholder' => '', '', 'required' => null));          
                
    echo                    '<tr>',
                    '<td colspan="2">',
                        '<input type="submit" name="btnChgInfoConnexion" value="Modifier">',
                        '<input type="reset" value="Annuler">',
                    '</td>',
                '</tr>',
            '</table>',
        '</form>',
    '</section>';
    
}

/**
 * Effectue le traitement pour le changement des informations de connexion d'un utilisateur.
 * 
 * @param int $id L'identifiant de l'utilisateur.
 * @param mysqli $bd La connexion à la base de données.
 * @param string $hashOldPasse Le hash du mot de passe actuel de l'utilisateur.
 * 
 * @return void
 */

function traitementChangeInfoConnexion($id,$bd,$hashOldPasse) : void {
    if (isset($_POST['btnChgInfoConnexion'])){
        $erreurs=[];
        $oldPasse= $_POST['oldPasse'] = trim($_POST['oldPasse']);
        $newPasse1 = $_POST['newPasse1'] = trim($_POST['newPasse1']);
        $newPasse2 = $_POST['newPasse2'] = trim($_POST['newPasse2']);
        $newLogin = $_POST['newLogin'] = trim($_POST['newLogin']);
        
      
        if(!password_verify($oldPasse,$hashOldPasse)){
            $erreurs[] = 'L\'ancien mot de passe doit correspondre.';
        }else{
            if ($newPasse1 !== $newPasse2) {
                $erreurs[] = 'Les mots de passe doivent être identiques.';
            }else{
                $nb = mb_strlen($newPasse1, encoding:'UTF-8');
                if ($nb < LMIN_PASSWORD || $nb > LMAX_PASSWORD){
                    $erreurs[] = 'Le mot de passe doit être constitué de '. LMIN_PASSWORD . ' à ' . LMAX_PASSWORD . ' caractères.';
                }
            }
        }
        
        if (!preg_match('/^[a-z][a-z0-9]{' . (LMIN_LOGIN - 1) . ',' .(LMAX_LOGIN - 1). '}$/u',$newLogin)) {
            $erreurs[] = 'Le login doit contenir entre '. LMIN_LOGIN .' et '. LMAX_LOGIN .
                        ' lettres minuscules sans accents, ou chiffres, et commencer par une lettre.';
        }

        if (count($erreurs) > 0) {
            if (is_array($erreurs)) {
                echo    '<div class="error">Les erreurs suivantes ont été relevées lors de votre inscription :',
                            '<ul>';
                foreach ($erreurs as $e) {
                    echo        '<li>', $e, '</li>';
                }
                echo        '</ul>',
                        '</div>';
            }
        }else{
            $hashNewMdp=password_hash($newPasse1, PASSWORD_DEFAULT);
            $sql="UPDATE usager SET usPasse= \"".$hashNewMdp."\" ,usLogin = \"".$newLogin."\" WHERE usID=".$id."; ";
            $res =  bdSendRequest($bd, $sql);
            $_SESSION['usLogin'] = $newLogin;
            echo "<p>Vos information de connexion ont bien été <strong>changé</strong></p>";

        }
    }

}

/**
 * Fonction qui gère la modification des informations personnelles 
 * 
 * @return void
 */
function modifierInfo() : void {
    $bd = bdConnect();

    $id = estAuthentifie() ? htmlProtegerSorties($_SESSION['usID']) : null;
    $oldInfo=[];
    echo "<section><h3>Modification des informations personnelles</h3>";
    $oldInfo=recupInfo($id,$bd);
    formChangeInfoPerso($oldInfo['usMail'],$oldInfo['usPrenom'],$oldInfo['usNom']);
    traitementChangeInfoPerso($id,$bd);
    echo"</section>";
    echo "<section><h3>Modification des informations de connexion</h3>";
    formChangeInfoConnexion($oldInfo['usLogin']);
    traitementChangeInfoConnexion($id,$bd,$oldInfo['usPasse']);
    echo"</section>";
    mysqli_close($bd);
}
