<?php


// chargement des bibliothèques de fonctions
require_once('bibli_erestou.php');
require_once('bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

// si l'utilisateur est déjà authentifié
if (estAuthentifie()){
   header ('Location: menu.php');
    exit();
}

// génération de la page
affEntete('Connexion');
affNav();


echo
'<section>',
	'<h3>Formulaire de connexion</h3>',
	'<p>Pour vous connecter, remplissez le formulaire ci-dessous. </p>';
	
traitementConnexion();
affFormConnexion();	


       
echo '<section>Pas encorer inscrit ? N\'attendez pas, <a href=\'inscription.php\'>inscrivez-vous</a> !</section>';


affPiedDePage();

// facultatif car fait automatiquement par PHP
ob_end_flush();


function afficherErreur() : void {
	echo    '<div class="error">Echec d\'authentification. Utilisateur inconnu ou mot de passe incorret.',
                '</div>';
}
function affFormConnexion() : void {
    
    if($_SERVER['HTTP_REFERER']!="http://localhost/erestou/php/connexion.php"){
        $_SESSION['LAST_HTTP'] = $_SERVER['HTTP_REFERER'];
    }
        
    echo
        '<form method="post" action="connexion.php">',
            '<table>';

    affLigneInput(  'Login :', array('type' => 'text', 'name' => 'login', 'value' => '',
                'placeholder' => '', ' lettres minuscules ou chiffres', 'required' => null));
    affLigneInput(  'Mot de passe :', array('type' => 'password', 'name' => 'passe', 'value' => '',
                'placeholder' => '' ,' caractères minimum', 'required' => null));
                
    echo                    '<tr>',
                    '<td colspan="2">',
                        '<input type="submit" name="btnConnexion" value="Se connecter">',
                        '<input type="reset" value="Annuler">',
                    '</td>',
                '</tr>',
            '</table>',
        '</form>',
    '</section>';
}

function traitementConnexion() : void {
    if (isset($_POST['btnConnexion'])){
		$bd = bdConnect();
		$login=$_POST['login'];
		$mdp=$_POST['passe'];
		$id="";
	    $sql = "SELECT usLogin, usPasse ,usID FROM usager WHERE usLogin = '{$login}' OR usPasse = '{$mdp}'";
	    $succes=true;
	 
		
	   $res =  bdSendRequest($bd, $sql);
	   
	      do{
			$tab = mysqli_fetch_assoc($res);
	      	if($tab==NULL){
				$succes=false;
	      		afficherErreur();
	      	}else{
				if ($tab['usLogin'] != $login){
					afficherErreur();
					$succes=false;
				}
				if (!password_verify($mdp,$tab['usPasse'])){
					afficherErreur();
					$succes=false;
				}
				$id =$tab['usID'];
			}
	    }while($tab = mysqli_fetch_assoc($res));
        
        if($succes){
		    $_SESSION['usID'] =$id;
		    $_SESSION['usLogin'] = $login;
		    
		    mysqli_free_result($res);
		    //fermeture de la connexion à la base de données
	   	   	mysqli_close($bd);
		    
            if(isset($_SESSION['LAST_HTTP'])){
		   	    header('Location: '.$_SESSION['LAST_HTTP']);
            }else{
                header("Location: menu.php");
            }

	     }
	     	
	   	mysqli_free_result($res);
		      // fermeture de la connexion à la base de données
	    mysqli_close($bd);
    }
}



