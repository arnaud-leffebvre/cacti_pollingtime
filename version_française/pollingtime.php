<?php
/*
 * Analyse Polling Time
 * Analyse le fichier de log Cacti courant et trace un graphe des compteurs
 * contenus dans les lignes "SYSTEM STATS: Time:".
 */

chdir('../../');
include('./include/auth.php');

/* ---------------------------------------------------------------------- */
/* Parametres de filtrage                                                 */
/* ---------------------------------------------------------------------- */
if (isset_request_var('records')) {
	$records = get_filter_request_var('records');
} else {
	$records = 100;
}

if (isset_request_var('scale') && get_nfilter_request_var('scale') == 'logarithmic') {
	$scale = 'logarithmic';
} else {
	$scale = 'linear';
}

/* Compteurs a extraire (ordre d'affichage) + palette de couleurs */
$pollingtime_metrics = array(
	'Time'            => '#e6194b',
	'Hosts'           => '#3cb44b',
	'HostsPerProcess' => '#4363d8',
	'DataSources'     => '#f58231',
	'RRDsProcessed'   => '#911eb4',
	'Processes'       => '#42d4f4',
	'Threads'         => '#f032e6',
	'Tholds'          => '#3cb44b',
	'TotalDevices'    => '#4363d8',
	'DownDevices'     => '#f58231',
	'NewDownDevices'  => '#911eb4',
	'Maps'            => '#3cb44b',
	'Warnings'        => '#f032e6',
	'SnmpTimeouts'    => '#e6194b'
);

/* Extraction des couples "Nom:Valeur" numeriques d'une ligne de log */
function pollingtime_extract_counters($line) {
	preg_match_all('/([A-Za-z]+):([0-9]+(?:\.[0-9]+)?)/', $line, $matches, PREG_SET_ORDER);

	$vals = array();
	foreach ($matches as $m) {
		$vals[$m[1]] = floatval($m[2]);
	}

	return $vals;
}

/* Construction des graphes (un graphe = un groupe de compteurs) */
function pollingtime_build_charts($groups, $dates, $rows, $metrics, $poller_interval) {
	$out = array();

	foreach ($groups as $group) {
		$datasets = array();

		foreach ($group['metrics'] as $name) {
			$color   = isset($metrics[$name]) ? $metrics[$name] : '#000000';
			$present = false;
			$data    = array();

			foreach ($rows as $r) {
				if (isset($r[$name])) {
					$data[]  = $r[$name];
					$present = true;
				} else {
					$data[] = null;
				}
			}

			if ($present) {
				$datasets[] = array(
					'label'           => $name,
					'data'            => $data,
					'borderColor'     => $color,
					'backgroundColor' => $color,
					'borderWidth'     => 1.5,
					'pointRadius'     => 0,
					'pointHoverRadius'=> 3,
					'tension'         => 0.1,
					'fill'            => false,
					'spanGaps'        => true
				);
			}
		}

		/* Ligne noire de reference : intervalle du poller */
		if (!empty($group['poller_line']) && $poller_interval > 0 && count($rows) > 0) {
			$datasets[] = array(
				'label'           => __('Poller Interval (%d s)', $poller_interval, 'pollingtime'),
				'data'            => array_fill(0, count($rows), $poller_interval),
				'borderColor'     => '#000000',
				'backgroundColor' => '#000000',
				'borderWidth'     => 2,
				'borderDash'      => array(6, 4),
				'pointRadius'     => 0,
				'pointHoverRadius'=> 0,
				'tension'         => 0,
				'fill'            => false,
				'spanGaps'        => true
			);
		}

		$out[] = array(
			'id'       => $group['id'],
			'title'    => $group['title'],
			'ylabel'   => $group['ylabel'],
			'labels'   => array_values($dates),
			'datasets' => $datasets
		);
	}

	return $out;
}

/* Plugins optionnels : on ne trace leurs graphes que s'ils sont installes ET actives */
$thold_enabled = function_exists('api_plugin_is_enabled') && api_plugin_is_enabled('thold');
$wm_enabled    = function_exists('api_plugin_is_enabled') && api_plugin_is_enabled('weathermap');

/* ---------------------------------------------------------------------- */
/* Lecture et analyse du fichier de log                                   */
/* ---------------------------------------------------------------------- */
$logfile = read_config_option('path_cactilog');

if ($logfile == '') {
	$logfile = $config['base_path'] . '/log/cacti.log';
}

$parse_error = '';
$dates       = array();
$rows        = array();   // chaque element = tableau assoc [compteur => valeur]
$thold_dates = array();
$thold_rows  = array();
$wm_dates    = array();
$wm_rows     = array();
$snmp_timeout_host_cycles = array();
$snmp_timeout_cycle_hosts = array();
$snmp_timeout_hosts       = array();   // host => array('count' => n, 'timeout' => '100 ms')

if (!is_readable($logfile)) {
	$parse_error = __('Le fichier de log "%s" est introuvable ou illisible par le serveur web.', $logfile, 'pollingtime');
} else {
	$fh = @fopen($logfile, 'r');

	if ($fh === false) {
		$parse_error = __('Impossible d\'ouvrir le fichier de log "%s".', $logfile, 'pollingtime');
	} else {
		$snmp_timeout_accum = 0;   // occurrences "SNMP timeout detected" depuis le dernier polling

		while (($line = fgets($fh)) !== false) {
			/* --- Polling principal : SYSTEM STATS: Time: --- */
			if (strpos($line, 'SYSTEM STATS: Time:') !== false) {
				$pos  = strpos($line, 'SYSTEM STATS:');
				$date = rtrim(trim(substr($line, 0, $pos)), " \t-");
				$vals = pollingtime_extract_counters($line);

				if (isset($vals['Time'])) {
					$vals['SnmpTimeouts'] = $snmp_timeout_accum;
					$dates[] = $date;
					$rows[]  = $vals;
					$snmp_timeout_host_cycles[] = $snmp_timeout_cycle_hosts;
				}

				$snmp_timeout_accum = 0;
				$snmp_timeout_cycle_hosts = array();

			/* --- Comptage des SNMP timeout du cycle de polling en cours --- */
			} elseif (strpos($line, 'SNMP timeout detected') !== false) {
				$occurrences = substr_count($line, 'SNMP timeout detected');
				$snmp_timeout_accum += $occurrences;

				/* Detail par equipement : Device[nom] ... SNMP timeout detected[valeur] */
				if (preg_match('/Device\[([^\]]+)\]/', $line, $dm) && preg_match('/SNMP timeout detected\s*\[([^\]]+)\]/', $line, $tm)) {
					$host    = $dm[1];
					$timeout = trim($tm[1]);

					if (!isset($snmp_timeout_cycle_hosts[$host])) {
						$snmp_timeout_cycle_hosts[$host] = array('count' => 0, 'timeout' => $timeout);
					}

					$snmp_timeout_cycle_hosts[$host]['count']  += $occurrences;
					$snmp_timeout_cycle_hosts[$host]['timeout'] = $timeout;
				}

			/* --- Plugin THOLD : SYSTEM THOLD STATS --- */
			} elseif ($thold_enabled && strpos($line, 'SYSTEM THOLD STATS') !== false) {
				$pos  = strpos($line, 'SYSTEM THOLD STATS');
				$date = rtrim(trim(substr($line, 0, $pos)), " \t-");
				$vals = pollingtime_extract_counters($line);

				if (isset($vals['Time'])) {
					$thold_dates[] = $date;
					$thold_rows[]  = $vals;
				}

			/* --- Plugin WEATHERMAP : SYSTEM STATS: WEATHERMAP --- */
			} elseif ($wm_enabled && strpos($line, 'SYSTEM STATS: WEATHERMAP') !== false) {
				$pos  = strpos($line, 'SYSTEM STATS: WEATHERMAP');
				$date = rtrim(trim(substr($line, 0, $pos)), " \t-");
				$vals = pollingtime_extract_counters($line);

				if (isset($vals['Time'])) {
					$wm_dates[] = $date;
					$wm_rows[]  = $vals;
				}
			}
		}

		fclose($fh);
	}
}

/* On ne conserve que les N derniers enregistrements */
$total = count($rows);
if ($records > 0 && $total > $records) {
	$rows  = array_slice($rows, -$records);
	$dates = array_slice($dates, -$records);
	$snmp_timeout_host_cycles = array_slice($snmp_timeout_host_cycles, -$records);
}

if ($records > 0 && count($thold_rows) > $records) {
	$thold_rows  = array_slice($thold_rows, -$records);
	$thold_dates = array_slice($thold_dates, -$records);
}

if ($records > 0 && count($wm_rows) > $records) {
	$wm_rows  = array_slice($wm_rows, -$records);
	$wm_dates = array_slice($wm_dates, -$records);
}

/* Cumul des timeout par equipement pour les cycles affiches uniquement */
foreach ($snmp_timeout_host_cycles as $cycle_hosts) {
	foreach ($cycle_hosts as $host => $info) {
		if (!isset($snmp_timeout_hosts[$host])) {
			$snmp_timeout_hosts[$host] = array('count' => 0, 'timeout' => $info['timeout']);
		}

		$snmp_timeout_hosts[$host]['count']  += $info['count'];
		$snmp_timeout_hosts[$host]['timeout'] = $info['timeout'];
	}
}

/* Tri des equipements par nombre de timeout decroissant */
uasort($snmp_timeout_hosts, function($a, $b) {
	return $b['count'] - $a['count'];
});

/* Correspondance id host (table host.id) => description (nom convivial) + snmp_version + ip */
$snmp_timeout_host_names   = array();
$snmp_timeout_host_snmpver = array();
$snmp_timeout_host_ip      = array();

if (count($snmp_timeout_hosts) > 0 && function_exists('db_fetch_assoc')) {
	$host_ids = array_filter(array_map('intval', array_keys($snmp_timeout_hosts)), function($id) {
		return $id > 0;
	});

	if (count($host_ids) > 0) {
		$host_rows = db_fetch_assoc('SELECT id, description, snmp_version, hostname FROM host WHERE id IN (' . implode(',', $host_ids) . ')');

		if (is_array($host_rows)) {
			foreach ($host_rows as $hr) {
				$snmp_timeout_host_names[$hr['id']]   = $hr['description'];
				$snmp_timeout_host_snmpver[$hr['id']] = $hr['snmp_version'];
				$snmp_timeout_host_ip[$hr['id']]      = $hr['hostname'];
			}
		}
	}
}

/* Valeur de l'intervalle du poller (en secondes) pour la ligne de reference */
$poller_interval = floatval(read_config_option('poller_interval'));

/* ---------------------------------------------------------------------- */
/* Definition des graphes (un graphe = un groupe de compteurs)            */
/* ---------------------------------------------------------------------- */
$pollingtime_groups = array(
	array(
		'id'          => 'pollingtimeChartTime',
		'title'       => __('Temps de polling / Hotes', 'pollingtime'),
		'ylabel'      => __('Secondes', 'pollingtime'),
		'metrics'     => array('Time', 'Hosts'),
		'poller_line' => true
	),
	array(
		'id'      => 'pollingtimeChartHpp',
		'title'   => __('Hotes par processus', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('HostsPerProcess')
	),
	array(
		'id'      => 'pollingtimeChartDs',
		'title'   => __('Sources de donnees / RRD traites', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('DataSources', 'RRDsProcessed')
	),
	array(
		'id'      => 'pollingtimeChartProc',
		'title'   => __('Processus / Threads', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('Processes', 'Threads')
	),
	array(
		'id'      => 'pollingtimeChartSnmpTimeout',
		'title'   => __('SNMP timeout detectes', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('SnmpTimeouts')
	)
);

/* Graphes du plugin THOLD (si installe et active et donnees presentes) */
$thold_groups = array(
	array(
		'id'          => 'pollingtimeTholdTime',
		'title'       => __('THOLD - Temps d\'execution', 'pollingtime'),
		'ylabel'      => __('Secondes', 'pollingtime'),
		'metrics'     => array('Time'),
		'poller_line' => true
	),
	array(
		'id'      => 'pollingtimeTholdCount',
		'title'   => __('THOLD - Seuils / Equipements', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('Tholds', 'TotalDevices')
	)
);

/* Graphes du plugin WEATHERMAP (si installe et active et donnees presentes) */
$wm_groups = array(
	array(
		'id'          => 'pollingtimeWmTime',
		'title'       => __('WEATHERMAP - Temps d\'execution', 'pollingtime'),
		'ylabel'      => __('Secondes', 'pollingtime'),
		'metrics'     => array('Time'),
		'poller_line' => true
	),
	array(
		'id'      => 'pollingtimeWmCount',
		'title'   => __('WEATHERMAP - Cartes / Alertes', 'pollingtime'),
		'ylabel'  => __('Nombre', 'pollingtime'),
		'metrics' => array('Maps', 'Warnings')
	)
);

/* Construction des series pour chaque graphe */
$charts = pollingtime_build_charts($pollingtime_groups, $dates, $rows, $pollingtime_metrics, $poller_interval);

if ($thold_enabled && count($thold_rows) > 0) {
	$charts = array_merge($charts, pollingtime_build_charts($thold_groups, $thold_dates, $thold_rows, $pollingtime_metrics, $poller_interval));
}

if ($wm_enabled && count($wm_rows) > 0) {
	$charts = array_merge($charts, pollingtime_build_charts($wm_groups, $wm_dates, $wm_rows, $pollingtime_metrics, $poller_interval));
}

$charts_js = json_encode($charts);

/* ---------------------------------------------------------------------- */
/* Affichage                                                              */
/* ---------------------------------------------------------------------- */
$title = __('Analyse Polling Time', 'pollingtime');

top_header();

/* --- Barre de filtres --- */
?>
<form id="form_pollingtime" method="get" action="pollingtime.php">
<?php
html_start_box(__('Analyse Polling Time', 'pollingtime'), '100%', '', '3', 'center', '');
?>
	<tr class='even noprint'>
		<td>
			<table class='filterTable'>
				<tr>
					<td><?php print __('Enregistrements', 'pollingtime');?></td>
					<td>
						<select id='records' name='records' onChange='applyFilter()'>
							<?php
							foreach (array(100, 500, 1000, 2000, 0) as $opt) {
								$label = ($opt == 0) ? __('Tous', 'pollingtime') : $opt;
								print "<option value='$opt'" . ($records == $opt ? " selected" : "") . ">$label</option>\n";
							}
							?>
						</select>
					</td>
					<td><?php print __('Echelle', 'pollingtime');?></td>
					<td>
						<select id='scale' name='scale' onChange='applyFilter()'>
							<option value='logarithmic'<?php print ($scale == 'logarithmic' ? ' selected' : '');?>><?php print __('Logarithmique', 'pollingtime');?></option>
							<option value='linear'<?php print ($scale == 'linear' ? ' selected' : '');?>><?php print __('Lineaire', 'pollingtime');?></option>
						</select>
					</td>
					<td>
						<input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Go', 'pollingtime');?>'>
						<a class='ui-button ui-corner-all ui-widget' href='pollingtime_help.php'><?php print __esc('Help', 'pollingtime');?></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
<?php
html_end_box();
?>
</form>

<script type='text/javascript'>
function applyFilter() {
	document.getElementById('form_pollingtime').submit();
}
</script>

<?php
if ($parse_error != '') {
	html_start_box(__('Temps de polling', 'pollingtime'), '100%', '', '3', 'center', '');
	print "<tr class='even'><td class='textError' style='padding:12px;'>" . html_escape($parse_error) . "</td></tr>";
	html_end_box();
} elseif (count($rows) == 0) {
	html_start_box(__('Temps de polling', 'pollingtime'), '100%', '', '3', 'center', '');
	print "<tr class='even'><td style='padding:12px;'>" . __('Aucune ligne "SYSTEM STATS: Time:" n\'a ete trouvee dans le fichier de log courant.', 'pollingtime') . "</td></tr>";
	html_end_box();
} else {
	print "<div class='pollingtimeHint noprint' style='padding:6px 2px;color:#888;'>" . __('Astuce : glissez-deposez les graphes par leur titre pour changer leur ordre.', 'pollingtime') . "</div>";
	print "<div id='pollingtimeCharts'>";

	foreach ($charts as $c) {
		$body_id  = $c['id'] . '_body';
		$add_text = array(
			array(
				'id'       => $c['id'] . '_toggle',
				'href'     => '#',
				'title'    => __('Afficher / Masquer le graphe', 'pollingtime'),
				'callback' => false,
				'class'    => 'fa fa-angle-double-up'
			)
		);
		print "<div class='pollingtimeChartWrapper' draggable='true' data-chart-id='" . html_escape($c['id']) . "'>";
		html_start_box($c['title'] . __(' - %d point(s)', count($c['labels']), 'pollingtime'), '100%', '', '3', 'center', $add_text);
		print "<tr class='even'><td style='padding:10px;'>";
		print "<div id='" . html_escape($body_id) . "' class='pollingtimeChartBody' style='position:relative;width:100%;height:320px;'><canvas id='" . html_escape($c['id']) . "'></canvas></div>";
		print "</td></tr>";
		html_end_box();
		print "</div>";

		/* Tableau detaille des SNMP timeout, affiche a cote de son graphe */
		if ($c['id'] === 'pollingtimeChartSnmpTimeout' && count($snmp_timeout_hosts) > 0) {
			$visible_limit = 15;
			$total_hosts   = count($snmp_timeout_hosts);

			print "<div class='pollingtimeChartWrapper'>";
			html_start_box(__('SNMP timeout par equipement', 'pollingtime'), '100%', '', '3', 'center', '');
			print "<tr class='tableHeader'><td>" . __('Equipement', 'pollingtime') . "</td><td>" . __('IP', 'pollingtime') . "</td><td>" . __('Nb timeout', 'pollingtime') . "</td><td>" . __('Timeout', 'pollingtime') . "</td><td>" . __('Snmp Version', 'pollingtime') . "</td></tr>";

			$row_class = 'odd';
			$row_index = 0;

			foreach ($snmp_timeout_hosts as $host => $info) {
				$display_name = (isset($snmp_timeout_host_names[$host]) && $snmp_timeout_host_names[$host] != '') ? $snmp_timeout_host_names[$host] : $host;
				$ip_address   = isset($snmp_timeout_host_ip[$host]) ? $snmp_timeout_host_ip[$host] : '';
				$snmp_version = isset($snmp_timeout_host_snmpver[$host]) ? $snmp_timeout_host_snmpver[$host] : '';
				$extra_attrs  = ($row_index >= $visible_limit) ? " class='$row_class pollingtimeSnmpExtra' style='display:none;'" : " class='$row_class'";
				print "<tr$extra_attrs><td>" . html_escape($display_name) . "</td><td>" . html_escape($ip_address) . "</td><td>" . intval($info['count']) . "</td><td>" . html_escape($info['timeout']) . "</td><td>" . html_escape($snmp_version) . "</td></tr>";
				$row_class = ($row_class == 'odd') ? 'even' : 'odd';
				$row_index++;
			}

			if ($total_hosts > $visible_limit) {
				$label_more = __('Afficher les %d autres equipements', $total_hosts - $visible_limit, 'pollingtime');
				$label_less = __('Afficher moins', 'pollingtime');
				print "<tr class='noprint'><td colspan='5' style='text-align:center;'>";
				print "<a href='#' id='pollingtimeSnmpToggle' data-label-more='" . html_escape($label_more) . "' data-label-less='" . html_escape($label_less) . "'>" . html_escape($label_more) . "</a>";
				print "</td></tr>";
			}

			html_end_box();
			print "</div>";
		}
	}

	print "</div>";
	print "<div id='pollingtimeChartError' class='textError' style='padding:8px;'></div>";
}

if (count($rows) > 0 && $parse_error == '') {
	?>
	<script type='text/javascript'>
	(function() {
		var charts = <?php print $charts_js;?>;
		var scale  = '<?php print $scale;?>';
		var chartInstances = {};

		function drawCharts() {
			if (typeof Chart === 'undefined') {
				document.getElementById('pollingtimeChartError').innerHTML =
					'La librairie Chart.js n\'a pas pu etre chargee. Placez chart.umd.min.js dans le repertoire du plugin ou autorisez le CDN.';
				return;
			}

			charts.forEach(function(c) {
				var el = document.getElementById(c.id);
				if (!el) { return; }

				chartInstances[c.id] = new Chart(el.getContext('2d'), {
					type: 'line',
					data: {
						labels: c.labels,
						datasets: c.datasets
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						interaction: { mode: 'index', intersect: false },
						plugins: {
							legend: { position: 'top' },
							tooltip: { enabled: true }
						},
						scales: {
							x: {
								ticks: { maxTicksLimit: 20, autoSkip: true, maxRotation: 60, minRotation: 0 },
								title: { display: true, text: 'Date' }
							},
							y: {
								type: scale,
								title: { display: true, text: c.ylabel }
							}
						}
					}
				});
			});
		}

		if (typeof Chart === 'undefined') {
			// 1) tentative de chargement local (offline), 2) repli sur le CDN
			var local = document.createElement('script');
			local.src = 'chart.umd.min.js';
			local.onload = drawCharts;
			local.onerror = function() {
				var cdn = document.createElement('script');
				cdn.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
				cdn.onload = drawCharts;
				cdn.onerror = drawCharts;
				document.head.appendChild(cdn);
			};
			document.head.appendChild(local);
		} else {
			drawCharts();
		}

		/* ---- Reorganisation des graphes par glisser-deposer ---- */
		var STORAGE_KEY = 'pollingtime_chart_order';
		var container   = document.getElementById('pollingtimeCharts');

		function wrappers() {
			return Array.prototype.slice.call(container.querySelectorAll('.pollingtimeChartWrapper'));
		}

		function saveOrder() {
			var order = wrappers().map(function(w) { return w.getAttribute('data-chart-id'); });
			try { localStorage.setItem(STORAGE_KEY, JSON.stringify(order)); } catch (e) {}
		}

		function applySavedOrder() {
			var saved;
			try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) { saved = null; }
			if (!saved || !saved.length) { return; }
			saved.forEach(function(id) {
				var w = container.querySelector('.pollingtimeChartWrapper[data-chart-id="' + id + '"]');
				if (w) { container.appendChild(w); }
			});
		}

		var dragged = null;

		function afterElement(y) {
			var els = wrappers().filter(function(w) { return w !== dragged; });
			var closest = { offset: -Infinity, element: null };
			els.forEach(function(w) {
				var box = w.getBoundingClientRect();
				var offset = y - box.top - box.height / 2;
				if (offset < 0 && offset > closest.offset) {
					closest = { offset: offset, element: w };
				}
			});
			return closest.element;
		}

		if (container) {
			wrappers().forEach(function(w) {
				w.style.cursor = 'move';

				w.addEventListener('dragstart', function() {
					dragged = w;
					setTimeout(function() { w.style.opacity = '0.4'; }, 0);
				});

				w.addEventListener('dragend', function() {
					w.style.opacity = '';
					dragged = null;
					saveOrder();
				});
			});

			container.addEventListener('dragover', function(e) {
				e.preventDefault();
				if (!dragged) { return; }
				var ref = afterElement(e.clientY);
				if (ref == null) {
					container.appendChild(dragged);
				} else {
					container.insertBefore(dragged, ref);
				}
			});

			applySavedOrder();
		}

		/* ---- Repli / depli de chaque graphe (etat memorise) ---- */
		var COLLAPSE_KEY = 'pollingtime_chart_collapsed';

		function loadCollapsed() {
			try { return JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]') || []; } catch (e) { return []; }
		}

		function saveCollapsed(list) {
			try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify(list)); } catch (e) {}
		}

		function setCollapsed(chartId, collapsed) {
			var body = document.getElementById(chartId + '_body');
			var a    = document.getElementById(chartId + '_toggle');
			if (!body) { return; }

			if (collapsed) {
				body.style.display = 'none';
			} else {
				body.style.display = '';
				if (chartInstances[chartId]) {
					chartInstances[chartId].resize();
				}
			}

			if (a) {
				var icon = a.querySelector('i');
				if (icon) {
					icon.className = collapsed ? 'fa fa-angle-double-down' : 'fa fa-angle-double-up';
				}
			}
		}

		var collapsedList = loadCollapsed();
		var toggleLinks   = container ? Array.prototype.slice.call(container.querySelectorAll('a[id$="_toggle"]')) : [];

		toggleLinks.forEach(function(a) {
			var chartId = a.id.replace(/_toggle$/, '');

			// etat initial memorise
			if (collapsedList.indexOf(chartId) !== -1) {
				setCollapsed(chartId, true);
			}

			a.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var body = document.getElementById(chartId + '_body');
				var isCollapsed = body && body.style.display === 'none';
				setCollapsed(chartId, !isCollapsed);

				var idx = collapsedList.indexOf(chartId);
				if (!isCollapsed) {
					if (idx === -1) { collapsedList.push(chartId); }
				} else if (idx !== -1) {
					collapsedList.splice(idx, 1);
				}
				saveCollapsed(collapsedList);
			});
		});

		/* ---- Deroulement du tableau "SNMP timeout par equipement" ---- */
		var snmpToggle = document.getElementById('pollingtimeSnmpToggle');

		if (snmpToggle) {
			snmpToggle.addEventListener('click', function(e) {
				e.preventDefault();

				var extraRows = document.getElementsByClassName('pollingtimeSnmpExtra');
				var expand    = extraRows.length > 0 && extraRows[0].style.display === 'none';
				var i;

				for (i = 0; i < extraRows.length; i++) {
					extraRows[i].style.display = expand ? '' : 'none';
				}

				snmpToggle.innerHTML = expand ? snmpToggle.getAttribute('data-label-less') : snmpToggle.getAttribute('data-label-more');
			});
		}
	})();
	</script>
	<?php
}

bottom_footer();
