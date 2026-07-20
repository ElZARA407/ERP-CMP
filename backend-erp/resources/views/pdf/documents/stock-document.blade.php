<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm 6mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 5.8pt;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .layout {
            width: 100%;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .layout tr,
        .copy-cell {
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        .copy-cell {
            width: 49%;
            vertical-align: top;
        }

        .gap {
            width: 2%;
        }

        .sheet {
            position: relative;
            width: 100%;
            height: 184mm;
            padding: 6mm 4mm 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        .top {
            width: 100%;
            margin-bottom: 8px;
        }

        .brand {
            width: 58%;
            vertical-align: top;
        }

        .brand-logo {
            width: 105px;
            max-height: 50px;
            object-fit: contain;
            margin-bottom: 2px;
        }

        .brand-name {
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 1.6px;
            color: #245086;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .brand-small {
            font-size: 4.7pt;
            line-height: 1.18;
        }

        .doc-head {
            width: 42%;
            text-align: center;
            vertical-align: top;
            padding-top: 1px;
        }

        .doc-title {
            font-size: 6.6pt;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .ref-label {
            width: 84px;
            margin: 0 auto;
            background: #f2f2f2;
            padding: 1px 0;
            font-size: 4.8pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ref-box {
            width: 84px;
            height: 17px;
            margin: 0 auto;
            background: #f2f2f2;
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 17px;
        }

        .meta-table {
            margin-top: 8px;
        }

        .meta-table td {
            padding: 1px 3px;
            vertical-align: middle;
        }

        .meta-label {
            width: 68px;
            font-size: 5.6pt;
        }

        .meta-box {
            height: 17px;
            border: 2px solid #000;
            font-size: 5.8pt;
            font-weight: 600;
        }

        .meta-box.center {
            text-align: center;
        }

        .side-label {
            width: 66px;
            font-size: 4.8pt;
            text-align: right;
            padding-right: 4px !important;
        }

        .lines {
            margin-top: 5px;
            table-layout: fixed;
        }

        .lines th,
        .lines td {
            border: 1.2px solid #000;
            padding: 1px 2px;
            vertical-align: middle;
        }

        .lines th {
            font-size: 4.75pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
        }

        .lines td {
            height: 14px;
            font-size: 5.2pt;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bottom-fixed {
            position: absolute;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
        }

        .signature-main {
            table-layout: fixed;
        }

        .signature-main td {
            border: 1.2px solid #000;
            height: 31px;
            vertical-align: top;
            padding: 2px 3px;
            font-size: 4.9pt;
        }

        .signature-label {
            font-weight: 700;
        }

        .received {
            table-layout: fixed;
            margin-top: 3px;
        }

        .received td {
            border: 1.2px solid #000;
            vertical-align: top;
        }

        .received-title {
            height: 14px;
            text-align: center;
            font-size: 4.9pt;
            font-weight: 700;
            padding-top: 2px;
        }

        .received-subtitle {
            text-align: center;
            font-size: 4.8pt;
            padding-bottom: 2px;
        }

        .received-box {
            height: 40px;
            padding: 3px;
            font-size: 4.9pt;
        }

        .footer {
            margin-top: 2px;
            font-size: 4.1pt;
            line-height: 1.2;
        }
    </style>
</head>
<body>
@php
    $metaRows = collect($document['meta_rows'] ?? []);

    $metaValue = function (string $label) use ($metaRows) {
        $row = $metaRows->first(fn ($item) => strtolower($item['label'] ?? '') === strtolower($label));
        return $row['value'] ?? '';
    };

    $officialReference = function (?string $reference) {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return '—';
        }

        return preg_replace('/^(BR|BL|BS)-20(\d{2})-(\d+)$/', '$1-$2-$3', $reference) ?: $reference;
    };

    $title = $document['title'] ?? 'DOCUMENT';
    $isReception = str_contains(strtoupper($title), 'RECEPTION');
    $partyLabel = $isReception ? 'Fournisseur/Client' : 'Client';

    $partyName = $metaValue('Fournisseur') ?: $metaValue('Client') ?: $metaValue('Location');
    $address = $metaValue('Adresse') ?: $metaValue('Location');
    $lines = collect($document['lines'] ?? [])->values();
@endphp

<table class="layout">
    <tr>
        @for ($copyIndex = 0; $copyIndex < 2; $copyIndex++)
            <td class="copy-cell">
                <div class="sheet">
                    <table class="top">
                        <tr>
                            <td class="brand">
                                @if (!empty($company['logo_src']))
                                    <img class="brand-logo" src="{{ $company['logo_src'] }}" alt="CMP">
                                @else
                                    <div class="brand-name">CMP</div>
                                    <div class="brand-name">Compagnie Malagasy<br>de Plastique</div>
                                @endif

                                @if (!empty($company['identification']))
                                    <div class="brand-small">{{ $company['identification'] }}</div>
                                @endif
                                @if (!empty($company['address']))
                                    <div class="brand-small">{{ $company['address'] }}</div>
                                @endif
                                @if (!empty($company['city']))
                                    <div class="brand-small">{{ $company['city'] }}</div>
                                @endif
                                @if (!empty($company['phone']) || !empty($company['email']))
                                    <div class="brand-small">
                                        {{ $company['phone'] ?? '' }}
                                        @if (!empty($company['phone']) && !empty($company['email'])) - @endif
                                        {{ $company['email'] ?? '' }}
                                    </div>
                                @endif
                            </td>

                            <td class="doc-head">
                                <div class="doc-title">{{ $title }}</div>
                                <div class="ref-label">REFERENCE</div>
                                <div class="ref-box">{{ $officialReference($document['reference_number'] ?? '') }}</div>
                            </td>
                        </tr>
                    </table>

                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Date</td>
                            <td class="meta-box center" style="width: 118px;">{{ $metaValue('Date') ?: ' / / ' }}</td>
                            <td></td>
                            <td class="side-label"></td>
                            <td style="width: 92px;"></td>
                        </tr>
                        <tr>
                            <td class="meta-label">{{ $partyLabel }}</td>
                            <td class="meta-box" colspan="2">{{ $partyName }}</td>
                            <td class="side-label">Référence BC</td>
                            <td class="meta-box">{{ $metaValue('Ref BC') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Adresse</td>
                            <td class="meta-box" colspan="2">{{ $address }}</td>
                            <td class="side-label">Référence Facture</td>
                            <td class="meta-box">{{ $metaValue('Ref facture') }}</td>
                        </tr>
                    </table>

                    <table class="lines">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 18px;">N°</th>
                                <th rowspan="2" style="width: 50px;">Référence</th>
                                <th rowspan="2">Désignation</th>
                                <th colspan="2" style="width: 105px;">Colisage</th>
                                <th colspan="2" style="width: 105px;">Quantité(s)</th>
                                <th rowspan="2" style="width: 18px;">V</th>
                            </tr>
                            <tr>
                                <th style="width: 58px;">Type</th>
                                <th style="width: 47px;">Qté</th>
                                <th style="width: 60px;">reçu(e) par colis</th>
                                <th style="width: 45px;">Totale</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $index => $line)
                                <tr>
                                    <td class="center">{{ $index + 1 }}</td>
                                    <td>{{ $line['reference'] ?? '' }}</td>
                                    <td>{{ $line['designation'] ?? '' }}</td>
                                    <td class="center">{{ $line['colisage_type'] ?? $line['colisage'] ?? '' }}</td>
                                    <td class="center">{{ $line['colisage_qte'] ?? '' }}</td>
                                    <td class="center">{{ $line['quantite_colis'] ?? '' }}</td>
                                    <td class="center">{{ $line['quantite'] ?? '' }}</td>
                                    <td class="center">{{ $line['validation'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="center">1</td>
                                    <td></td>
                                    <td></td>
                                    <td class="center"></td>
                                    <td class="center"></td>
                                    <td class="center"></td>
                                    <td class="center"></td>
                                    <td class="center"></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="bottom-fixed">
                        <table class="signature-main">
                            <tr>
                                <td>
                                    <span class="signature-label">Émis par :</span>
                                </td>
                                <td>
                                    <span class="signature-label">Vérifié :</span>
                                </td>
                                <td>
                                    <span class="signature-label">Approuvé par :</span>
                                </td>
                            </tr>
                        </table>

                        <table class="received">
                            <tr>
                                <td style="width: 42%;">
                                    <div class="received-title">Livré(e) par</div>
                                </td>
                                <td rowspan="3">
                                    <div class="received-title">Reçu les marchandises ci-dessus conforme et en bon état ce</div>
                                    <div class="received-subtitle">Par CMP &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Par client</div>
                                    <div class="received-box">
                                        Prénom<br>
                                        Signature - Réceptionnaire Usine<br>
                                        Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table>
                                        <tr>
                                            <td style="border: 0; border-right: 1.2px solid #000; height: 34px; width: 38%; font-size: 4.9pt;">Prénom</td>
                                            <td style="border: 0; height: 34px; text-align: center; font-size: 4.9pt;">Signature</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 13px; font-size: 4.9pt;">
                                    N° VÉHICULE<br>
                                    Transporteur
                                </td>
                            </tr>
                        </table>

                        <div class="footer">
                            (1) Exemplaire blanc pour Client
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            (2) Exemplaire bleu pour Comptabilité
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            (3) Exemplaire vert pour carnet BL usine
                        </div>
                    </div>
                </div>
            </td>

            @if ($copyIndex === 0)
                <td class="gap"></td>
            @endif
        @endfor
    </tr>
</table>
</body>
</html>