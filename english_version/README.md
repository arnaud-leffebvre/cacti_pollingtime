# Polling Time Analysis (pollingtime)

Cacti plugin that analyzes the Cacti log file to plot the evolution of poller
performance over time. It looks for the statistics lines generated at each
polling cycle (for example `SYSTEM STATS: Time:`) and displays the extracted
counters as interactive charts.

- **Version:** 1.0.4
- **Author:** Arnaud LEFFEBVRE
- **Compatibility:** Cacti 1.2.x (tested on 1.2.30 / PHP 8.2 / MariaDB 10.4 / RHEL 8)

## How It Works

Each time the main page is displayed, the plugin reads the configured log
file (`path_cactilog`, or `log/cacti.log` by default) and extracts the
`Name:Value` pairs found in the system statistics lines. Only the last N
records are kept and plotted, based on the selected filter.

## Available Charts

- **Polling Time / Hosts**: duration of a polling cycle and number of hosts
  processed, with a reference line matching the configured poller interval.
- **Hosts per Process**: distribution of hosts across the polling processes.
- **Data Sources / RRDs Processed**: volume of data processed at each cycle.
- **Processes / Threads**: number of processes and threads used by the poller.
- **SNMP Timeouts Detected**: number of `SNMP timeout detected` occurrences
  per cycle, with a table detailing timeouts per device (name, IP, count,
  timeout value, SNMP version).

If the THOLD and/or WEATHERMAP plugins are installed and enabled, and their
own statistics lines are present in the log, additional charts are dedicated
to them (execution time, thresholds/devices for THOLD, maps/alerts for
WEATHERMAP).

## Available Filters

- **Records**: number of most recent polling cycles to display (100, 500,
  1000, 2000, or All).
- **Scale**: linear or logarithmic for the value axis.

## Usage Tips

- Charts can be reordered by dragging and dropping their title; the chosen
  order is remembered in the browser.
- Each chart can be individually collapsed/expanded using the icon in its
  title; the state is also remembered.
- The SNMP timeouts per device table only shows the first 15 rows by
  default; a link lets you display the remaining devices.

## Requirements

- The Cacti log file must be readable by the web server.
- Access to the main page and this help page requires the "Polling Time
  Analysis" permission on the user account.

## Installation

1. Copy the `pollingtime` directory into Cacti's `plugins/` directory.
2. Install and enable the plugin from **Console > Configuration > Plugin
   Management**.
3. The "Polling Time Analysis" entry appears in the **Management** menu.
