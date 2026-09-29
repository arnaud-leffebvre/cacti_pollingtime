<?php
/*
 * Help - Polling Time Analysis
 * Plugin explanation page, accessible from the "Plugin Name" column
 * of Cacti's "Plugin Management" table.
 */

chdir('../../');
include('./include/auth.php');

$title = __('Help - Polling Time Analysis', 'pollingtime');

top_header();

html_start_box(__('About the "Polling Time Analysis" plugin', 'pollingtime'), '100%', '', '3', 'center', '');
?>
	<tr class='even'>
		<td class='textArea' style='padding:14px;'>

			<p><?php print __('This plugin analyzes the Cacti log file to plot the evolution of poller performance over time. It looks for the statistics lines generated at each polling cycle (for example "SYSTEM STATS: Time:") and displays the extracted counters as interactive charts.', 'pollingtime');?></p>

			<h2><?php print __('How It Works', 'pollingtime');?></h2>
			<p><?php print __('Each time the main page is displayed, the plugin reads the configured log file ("path_cactilog", or "log/cacti.log" by default) and extracts the "Name:Value" pairs found in the system statistics lines. Only the last N records are kept and plotted, based on the selected filter.', 'pollingtime');?></p>

			<h2><?php print __('Available Charts', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Polling Time / Hosts: duration of a polling cycle and number of hosts processed, with a reference line matching the configured poller interval.', 'pollingtime');?></li>
				<li><?php print __('Hosts per Process: distribution of hosts across the polling processes.', 'pollingtime');?></li>
				<li><?php print __('Data Sources / RRDs Processed: volume of data processed at each cycle.', 'pollingtime');?></li>
				<li><?php print __('Processes / Threads: number of processes and threads used by the poller.', 'pollingtime');?></li>
				<li><?php print __('SNMP Timeouts Detected: number of "SNMP timeout detected" occurrences per cycle, with a table detailing timeouts per device (name, IP, count, timeout value, SNMP version).', 'pollingtime');?></li>
			</ul>
			<p><?php print __('If the THOLD and/or WEATHERMAP plugins are installed and enabled, and their own statistics lines are present in the log, additional charts are dedicated to them (execution time, thresholds/devices for THOLD, maps/alerts for WEATHERMAP).', 'pollingtime');?></p>

			<h2><?php print __('Available Filters', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Records: number of most recent polling cycles to display (100, 500, 1000, 2000, or All).', 'pollingtime');?></li>
				<li><?php print __('Scale: linear or logarithmic for the value axis.', 'pollingtime');?></li>
			</ul>

			<h2><?php print __('Usage Tips', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('Charts can be reordered by dragging and dropping their title; the chosen order is remembered in the browser.', 'pollingtime');?></li>
				<li><?php print __('Each chart can be individually collapsed/expanded using the icon in its title; the state is also remembered.', 'pollingtime');?></li>
				<li><?php print __('The SNMP timeouts per device table only shows the first 15 rows by default; a link lets you display the remaining devices.', 'pollingtime');?></li>
			</ul>

			<h2><?php print __('Requirements', 'pollingtime');?></h2>
			<ul>
				<li><?php print __('The Cacti log file must be readable by the web server.', 'pollingtime');?></li>
				<li><?php print __('Access to this page and the plugin\'s main page requires the "Polling Time Analysis" permission on the user account.', 'pollingtime');?></li>
			</ul>

			<p style='padding-top:10px;'>
				<a class='ui-button ui-corner-all ui-widget' href='pollingtime.php'><?php print __esc('Back to Charts', 'pollingtime');?></a>
			</p>

		</td>
	</tr>
<?php
html_end_box();

bottom_footer();
