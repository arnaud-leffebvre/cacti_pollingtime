# Analyse Polling Time (pollingtime)

Plugin Cacti qui analyse le fichier de log de Cacti afin de tracer
l'evolution des performances du poller au fil du temps. Il recherche les
lignes de statistiques generees a chaque cycle de polling (par exemple
`SYSTEM STATS: Time:`) et affiche les compteurs extraits sous forme de
graphes interactifs.

- **Version :** 1.0.4
- **Auteur :** Arnaud LEFFEBVRE
- **Compatibilite :** Cacti 1.2.x (teste sur 1.2.30 / PHP 8.2 / MariaDB 10.4 / RHEL 8)

## Fonctionnement

A chaque affichage de la page principale, le plugin lit le fichier de log
configure (`path_cactilog`, ou `log/cacti.log` par defaut) et extrait les
couples `Nom:Valeur` presents sur les lignes de statistiques systeme. Seuls
les N derniers enregistrements sont conserves et traces, selon le filtre
choisi.

## Graphes disponibles

- **Temps de polling / Hotes** : duree d'un cycle de polling et nombre
  d'hotes traites, avec une ligne de reference correspondant a l'intervalle
  du poller configure.
- **Hotes par processus** : repartition des hotes entre les processus de
  polling.
- **Sources de donnees / RRD traites** : volume de donnees traite a chaque
  cycle.
- **Processus / Threads** : nombre de processus et de threads utilises par
  le poller.
- **SNMP timeout detectes** : nombre d'occurrences `SNMP timeout detected`
  par cycle, avec un tableau detaillant les timeouts par equipement (nom,
  IP, nombre, valeur du timeout, version SNMP).

Si les plugins THOLD et/ou WEATHERMAP sont installes et actives, et que
leurs propres lignes de statistiques sont presentes dans le log, des
graphes supplementaires leur sont dedies (temps d'execution,
seuils/equipements pour THOLD, cartes/alertes pour WEATHERMAP).

## Filtres disponibles

- **Enregistrements** : nombre de derniers cycles de polling a afficher
  (100, 500, 1000, 2000 ou Tous).
- **Echelle** : lineaire ou logarithmique pour l'axe des valeurs.

## Astuces d'utilisation

- Les graphes peuvent etre reordonnes par glisser-deposer sur leur titre ;
  l'ordre choisi est memorise dans le navigateur.
- Chaque graphe peut etre replie/deplie individuellement via l'icone
  situee dans son titre ; l'etat est egalement memorise.
- Le tableau des timeouts SNMP par equipement n'affiche que les 15
  premieres lignes par defaut ; un lien permet d'afficher les equipements
  restants.

## Pre-requis

- Le fichier de log Cacti doit etre lisible par le serveur web.
- L'acces a la page principale et a cette page d'aide necessite
  l'autorisation "Analyse Polling Time" sur le compte utilisateur.

## Installation

1. Copiez le repertoire `pollingtime` dans le repertoire `plugins/` de
   Cacti.
2. Installez et activez le plugin depuis **Console > Configuration >
   Plugin Management**.
3. L'entree "Analyse Polling Time" apparait dans le menu **Management**.
