<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $record->number }}</title>
<style>
@page { margin: 32px 34px 42px; }
body { font-family: DejaVu Sans, sans-serif; color:#172540; font-size:10px; }
h1 { font-size:24px; margin:8px 0; } h2 { font-size:14px; margin:8px 0; }
.kicker { color:#3267de; font-size:11px; letter-spacing:2px; } .muted { color:#596579; }
.header { border-bottom:3px solid #3267de; padding-bottom:16px; } .status { text-transform:uppercase; }
.details { width:100%; margin:18px 0; } .details td { vertical-align:top; width:50%; }
table.lines { width:100%; border-collapse:collapse; font-size:8px; } .lines th { background:#eef2fa; text-align:left; padding:8px 4px; }
.lines td { border-bottom:1px solid #e4e9f1; padding:8px 4px; vertical-align:top; overflow-wrap:break-word; }
tr { page-break-inside:avoid; } thead { display:table-header-group; } .right { text-align:right; }
.totals { width:55%; margin:18px 0 18px auto; } .totals td { padding:5px; } .grand { background:#eef2fa; font-weight:bold; font-size:13px; }
.terms { white-space:pre-wrap; word-wrap:break-word; } .notice { border-top:1px solid #e4e9f1; padding-top:10px; margin-top:18px; }
</style></head><body>
<div class="header"><div class="kicker">INFOVISH · SALES</div><h1>{{ $order ? 'Sales order' : 'Quotation' }}</h1>
<strong>{{ $record->number }}</strong> · <span class="status">{{ $record->status }}</span>
@if($record->status === 'draft')<h2>DRAFT — FOR REVIEW</h2>@endif
<p>{{ $record->title }}</p></div>
<table class="details"><tr><td><div class="muted">FROM</div><h2>{{ $record->snapshot['seller']['seller_name'] }}</h2>
<div class="terms">{{ $record->snapshot['seller']['seller_address'] }}</div>
@if($record->snapshot['seller']['vat_registered'])<p>TIN: {{ $record->snapshot['seller']['tin'] }}<br>VAT registration: {{ $record->snapshot['seller']['vat_registration'] }}</p>@else<p>Supplier declared not VAT registered</p>@endif
</td><td><div class="muted">CUSTOMER</div><h2>{{ $record->snapshot['customer']['name'] }}</h2><p>{{ $record->snapshot['customer']['number'] }}</p>
<p>Quotation date: {{ $record->snapshot['issue_date'] }}<br>Valid until: {{ $record->snapshot['valid_until'] }}</p>
@if($order)<p>Quotation: {{ $record->snapshot['quotation_number'] }}<br>Confirmed: {{ $record->confirmed_at }}</p>@endif
</td></tr></table>
<table class="lines"><thead><tr><th style="width:27%">Item / description</th><th>Quantity / unit</th><th>Unit price</th><th>Discount %</th><th>VAT treatment</th><th class="right">Net · LKR</th></tr></thead><tbody>
@foreach($record->snapshot['pricing']['lines'] as $line)<tr><td><strong>{{ $line['sku'] }}</strong><br>{{ $line['name'] }}</td><td>{{ $line['quantity'] }}<br>{{ $line['unit'] }}</td><td>{{ $line['unit_price'] }}</td><td>{{ $line['discount_percent'] }}</td><td>{{ $line['tax_code'] }}<br>{{ $line['vat_rate'] }}%@if($line['tax_basis'])<br>{{ $line['tax_basis'] }}@endif</td><td class="right">{{ $line['net'] }}</td></tr>@endforeach
</tbody></table>
<table class="totals"><tr><td>Net, excluding VAT</td><td class="right">LKR {{ $record->net }}</td></tr><tr><td>VAT18 base</td><td class="right">LKR {{ $record->snapshot['pricing']['standard_base'] }}</td></tr><tr><td>VAT</td><td class="right">LKR {{ $record->vat }}</td></tr><tr class="grand"><td>Total</td><td class="right">LKR {{ $record->total }}</td></tr></table>
@if($record->snapshot['terms'])<h2>Terms</h2><div class="terms">{{ $record->snapshot['terms'] }}</div>@endif
<div class="notice"><strong>Not a tax invoice.</strong> This document records {{ $order ? 'an order confirmation' : 'a commercial quotation' }}. It does not record payment, delivery or an accounting posting.
<p class="muted">Tax rule {{ $record->snapshot['pricing']['rule_version'] }} · Prices exclude VAT. Net lines and document VAT round half-up to LKR cents.</p></div>
</body></html>
