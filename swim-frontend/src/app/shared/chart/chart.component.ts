import { Component, DestroyRef, ElementRef, afterNextRender, effect, inject, input, viewChild } from '@angular/core';
import * as echarts from 'echarts/core';
import type { EChartsCoreOption, ECharts } from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer]);

/**
 * Series colours in fixed order — checked against the dark card surface (#1c1c1c) for lightness band,
 * colour-blind separation and contrast. The brand gold (#FFD700) is too light for data marks.
 */
export const CHART_COLORS = ['#AD8E00', '#3A8FD1'] as const;

function cssVar(name: string, fallback: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

/** Theme shared by every chart: text and grid wear the app's text tokens, never a series colour. */
export function chartTheme() {
  const text   = cssVar('--swim-text', '#e0e0e0');
  const muted  = cssVar('--swim-muted', '#888');
  const border = cssVar('--swim-border', '#2a2a2a');
  return {
    text, muted, border,
    base: {
      backgroundColor: 'transparent',
      textStyle: { color: text, fontFamily: 'inherit' },
      color: [...CHART_COLORS],
      grid: { left: 4, right: 20, top: 8, bottom: 4, containLabel: true },
      tooltip: {
        backgroundColor: cssVar('--swim-card', '#1c1c1c'), borderColor: border,
        textStyle: { color: text, fontSize: 13 },
      },
    },
    /** Value axis: no line, faint grid, muted labels. */
    valueAxis: {
      axisLine: { show: false }, axisTick: { show: false },
      axisLabel: { color: muted, fontSize: 12 }, splitLine: { lineStyle: { color: border } },
    },
    /** Category / time axis: muted line, readable labels, no grid. */
    labelAxis: {
      axisLine: { lineStyle: { color: border } }, axisTick: { show: false },
      axisLabel: { color: text, fontSize: 13 }, splitLine: { show: false },
    },
  };
}

/** Thin ECharts wrapper: renders `options`, follows its container's size, disposes on destroy. */
@Component({
  selector: 'app-chart',
  template: `<div #host class="chart" role="img" [attr.aria-label]="label()" [style.height]="height()"></div>`,
  styles: [`:host { display: block; min-width: 0; } .chart { width: 100%; }`],
})
export class ChartComponent {
  readonly options = input.required<EChartsCoreOption>();
  readonly height  = input('320px');
  /** Accessible name — the chart's data is also shown as a table on the same page. */
  readonly label   = input('Wykres');

  private host  = viewChild.required<ElementRef<HTMLDivElement>>('host');
  private chart: ECharts | null = null;

  constructor() {
    const destroyRef = inject(DestroyRef);

    afterNextRender(() => {
      const el = this.host().nativeElement;
      this.chart = echarts.init(el, null, { renderer: 'canvas' });
      this.chart.setOption(this.options(), { notMerge: true });
      // A category axis with `triggerEvent: true`: hovering a label shows the tooltip of its data point
      // (checked in the handler — a { targetType } query is not applied to axis events)
      type AxisLabelEvent = { targetType?: string; dataIndex?: number };
      this.chart.on('mouseover', (e: AxisLabelEvent) => {
        if (e.targetType === 'axisLabel' && e.dataIndex !== undefined) {
          this.chart?.dispatchAction({ type: 'showTip', seriesIndex: 0, dataIndex: e.dataIndex });
        }
      });
      this.chart.on('mouseout', (e: AxisLabelEvent) => {
        if (e.targetType === 'axisLabel') this.chart?.dispatchAction({ type: 'hideTip' });
      });
      // Also covers height changes driven by the [height] input
      const ro = new ResizeObserver(() => this.chart?.resize());
      ro.observe(el);
      destroyRef.onDestroy(() => { ro.disconnect(); this.chart?.dispose(); this.chart = null; });
    });

    effect(() => {
      const opts = this.options();
      this.chart?.setOption(opts, { notMerge: true });
    });
  }
}
