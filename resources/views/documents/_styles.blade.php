{{-- Shared print styles. Dompdf supports a limited CSS subset, so these are deliberately plain. --}}
<style>
    @page { margin: 22mm 18mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #1c2530; line-height: 1.45; }
    h1, h2, h3 { margin: 0 0 6px; }
    .muted { color: #5b6875; }
    .small { font-size: 9pt; }
    .mono { font-family: "DejaVu Sans Mono", monospace; }
    .right { text-align: right; }
    .center { text-align: center; }
    .letterhead { border-bottom: 2px solid #0f4c81; padding-bottom: 10px; margin-bottom: 16px; }
    .letterhead .mall { font-size: 16pt; font-weight: bold; color: #0f4c81; }
    .letterhead .meta { font-size: 9pt; color: #5b6875; }
    .doc-title { font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; margin: 18px 0 10px; }
    table { width: 100%; border-collapse: collapse; }
    table.detail td { padding: 3px 0; vertical-align: top; }
    table.detail td.k { width: 34%; color: #5b6875; }
    table.lines th, table.lines td { border-bottom: 1px solid #dde3ea; padding: 6px 4px; text-align: left; }
    table.lines th { background: #f2f5f8; font-size: 9pt; text-transform: uppercase; letter-spacing: .04em; }
    table.lines td.num, table.lines th.num { text-align: right; }
    table.lines tr.total td { border-top: 2px solid #0f4c81; border-bottom: none; font-weight: bold; }
    .terms { font-size: 9pt; white-space: pre-line; margin-top: 6px; }
    .signature { margin-top: 34px; }
    .signature .line { border-top: 1px solid #1c2530; width: 200px; padding-top: 4px; font-size: 9pt; }
    .footnote { margin-top: 22px; border-top: 1px solid #dde3ea; padding-top: 8px; font-size: 8pt; color: #5b6875; }
    .stamp { display: inline-block; border: 2px solid #1b7f4b; color: #1b7f4b; padding: 4px 12px;
             font-weight: bold; letter-spacing: .08em; font-size: 10pt; }
    .stamp.void { border-color: #b3261e; color: #b3261e; }
</style>
