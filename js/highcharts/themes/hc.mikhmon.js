/**
 * Mikhmon theme for Highcharts: reads the colours from the page's CSS variables,
 * so charts always match the selected mode and accent (replaces hc.<theme>.js).
 * Re-applied when js/mikhmon-shell.js fires "mikhmon:theme".
 */
(function () {
  'use strict';
  function css(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }

  function options() {
    var text = css('--text'), muted = css('--muted'), border = css('--border'), surface = css('--surface');
    var font = css('--font') || 'system-ui, sans-serif';
    var axis = {
      labels: { style: { color: muted } },
      title: { style: { color: muted } },
      lineColor: border, tickColor: border, gridLineColor: border
    };
    return {
      colors: [css('--accent'), css('--series-2')],
      chart: {
        backgroundColor: 'transparent', plotBackgroundColor: 'transparent', borderWidth: 0, height: 300,
        style: { fontFamily: font }
      },
      title: { style: { color: text, fontSize: '14px', fontWeight: '650' } },
      subtitle: { style: { color: muted, fontSize: '12px' } },
      xAxis: axis,
      yAxis: axis,
      legend: { itemStyle: { color: text, fontWeight: '500' }, itemHoverStyle: { color: css('--accent') }, itemHiddenStyle: { color: muted } },
      tooltip: { backgroundColor: surface, borderColor: border, borderRadius: 8, style: { color: text } },
      plotOptions: { series: { marker: { lineColor: surface } }, areaspline: { fillOpacity: 0.18 }, area: { fillOpacity: 0.18 } },
      credits: { enabled: false }
    };
  }

  Highcharts.theme = options();
  Highcharts.setOptions(Highcharts.theme);

  document.addEventListener('mikhmon:theme', function () {
    var o = options();
    Highcharts.theme = o;
    Highcharts.setOptions(o);
    // do not touch a chart's own height (the traffic monitor sets 500) when only colours change
    delete o.chart.height;
    (Highcharts.charts || []).forEach(function (c) { if (c) { c.update(o, true); } });
  });
})();
