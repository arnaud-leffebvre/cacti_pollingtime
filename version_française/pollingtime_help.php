<?php
/*
 * Aide - Analyse Polling Time
 * Page d'explication du plugin, accessible depuis la colonne "Plugin Name"
 * du tableau "Plugin Management" de Cacti.
 */

chdir('../../');
include('./include/auth.php');

$title = __('Aide - Analyse Polling Time', 'pollingtime');

top_header();

html_start_box(__('A propos du plugin "Analyse Polling Time"', 'pollingtime'), '100%', '', '3', 'center', '');
?>
	<tr class='even'>
		<td class='textArea' style='padding:14px;'>

			<p><?php print __('Ce plugin analyse le fichier de log de Cacti afin de tracer l\'evolution des performances du poller au fil du temps. Il recherche les lignes de statistiques generees a chaque cycle de polling (par exemple "SYSTEM STATS: Time:") et affiche les compteurs extraits sous forme de graphes interactifs.', 'pollingtime');?></p>

			<h2><?php print __('Fonctionnement', 'pollingtime');?></h2>
			<p><?php print __('A chaque affichage de la page principale, le plugin lit le fichier de log configure ("path_cactilog", ou "log/cacti.log" par defaut) et extrait les couples "Nom:Valeur" presents sur les lignes de statistiques systeme. Seuls les N derniers enregistrements sont conserves et traces, selon le filtre choisi.', 'pollingtime');?></p>

			<h2><?php print __('Graphes disponibles', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Temps de polling / Hotes : duree d\'un cycle de polling et nombre d\'hotes traites, avec une ligne de reference correspondant a l\'intervalle du poller configure.', 'pollingtime');?></li>
				<li><?php print __('Hotes par processus : repartition des hotes entre les processus de polling.', 'pollingtime');?></li>
				<li><?php print __('Sources de donnees / RRD traites : volume de donnees traite a chaque cycle.', 'pollingtime');?></li>
				<li><?php print __('Processus / Threads : nombre de processus et de threads utilises par le poller.', 'pollingtime');?></li>
				<li><?php print __('SNMP timeout detectes : nombre d\'occurrences "SNMP timeout detected" par cycle, avec un tableau detaillant les timeouts par equipement (nom, IP, nombre, valeur du timeout, version SNMP).', 'pollingtime');?></li>
			</ul>
			<p><?php print __('Si les plugins THOLD et/ou WEATHERMAP sont installes et actives, et que leur propres lignes de statistiques sont presentes dans le log, des graphes supplementaires leur sont dedies (temps d\'execution, seuils/equipements pour THOLD, cartes/alertes pour WEATHERMAP).', 'pollingtime');?></p>

			<h2><?php print __('Filtres disponibles', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Enregistrements : nombre de derniers cycles de polling a afficher (100, 500, 1000, 2000 ou Tous).', 'pollingtime');?></li>
				<li><?php print __('Echelle : lineaire ou logarithmique pour l\'axe des valeurs.', 'pollingtime');?></li>
			</ul>

			<h2><?php print __('Astuces d\'utilisation', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Les graphes peuvent etre reordonnes par glisser-deposer sur leur titre ; l\'ordre choisi est memorise dans le navigateur.', 'pollingtime');?></li>
				<li><?php print __('Chaque graphe peut etre replie/deplie individuellement via l\'icone situee dans son titre ; l\'etat est egalement memorise.', 'pollingtime');?></li>
				<li><?php print __('Le tableau des timeouts SNMP par equipement n\'affiche que les 15 premieres lignes par defaut ; un lien permet d\'afficher les equipements restants.', 'pollingtime');?></li>
			</ul>

			<h2><?php print __('Pre-requis', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Le fichier de log Cacti doit etre lisible par le serveur web.', 'pollingtime');?></li>
				<li><?php print __('L\'acces a cette page et a la page principale du plugin necessite l\'autorisation "Analyse Polling Time" sur le compte utilisateur.', 'pollingtime');?></li>
			</ul>

			<p style='padding-top:10px;'>
				<a class='ui-button ui-corner-all ui-widget' href='pollingtime.php'><?php print __esc('Retour aux graphes', 'pollingtime');?></a>
			</p>

		</td>
	</tr>
<?php
html_end_box();

bottom_footer();
