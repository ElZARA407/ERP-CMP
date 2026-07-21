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
            padding: 5mm 4mm 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        .top {
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }

        .company {
            width: 56%;
            vertical-align: top;
        }

        .company-name {
            font-size: 6.7pt;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .company-line {
            font-size: 5.1pt;
            line-height: 1.25;
        }

        .logo-cell {
            width: 44%;
            text-align: center;
            vertical-align: top;
        }

        .brand-logo {
            width: 150px;
            max-height: 100px;
            object-fit: contain;
        }

        .client-block {
            margin-top: 8px;
            margin-left: auto;
            width: 38%;
        }

        .client-block td {
            padding: 2px 4px;
            font-size: 5.5pt;
            vertical-align: middle;
        }

        .client-label {
            width: 42px;
            font-weight: 700;
        }

        .client-value {
            background: #dff0d8;
            min-height: 13px;
        }

        .title-box {
            margin-top: 8px;
            border: 2px solid #000;
            text-align: center;
            font-size: 8.4pt;
            font-weight: 700;
            text-transform: uppercase;
            height: 16px;
            line-height: 13px;
        }

        .subtitle {
            text-align: center;
            font-size: 4.8pt;
            font-style: italic;
            margin-top: 1px;
        }

        .bl-box {
            margin-top: 5px;
            width: 35%;
        }

        .bl-box td {
            border: 1.2px solid #000;
            padding: 2px 3px;
            font-size: 5.2pt;
        }

        .bl-head {
            text-align: center;
            font-weight: 700;
        }

        .lines {
            margin-top: 4px;
            table-layout: fixed;
        }

        .lines th,
        .lines td {
            border: 1.1px solid #000;
            padding: 1px 3px;
            vertical-align: middle;
        }

        .lines th {
            background: #ffff00;
            font-size: 5.2pt;
            font-weight: 700;
            text-align: center;
        }

        .lines td {
            height: 12px;
            font-size: 5.15pt;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .totals {
            width: 39%;
            margin-left: auto;
            table-layout: fixed;
        }

        .totals td {
            border: 1.1px solid #000;
            padding: 1px 3px;
            font-size: 5.2pt;
            height: 12px;
        }

        .total-label {
            font-weight: 700;
            text-align: center;
        }

        .amount-words {
            margin-top: 12px;
            margin-left: 40px;
            font-size: 5.2pt;
            line-height: 1.35;
        }

        .payment {
            margin-top: 8px;
            margin-left: 40px;
            font-size: 5.2pt;
            line-height: 1.35;
        }

        .date-place {
            margin-top: 11px;
            margin-left: 40px;
            font-size: 5.2pt;
        }

        .bottom-fixed {
            position: absolute;
            left: 5mm;
            right: 5mm;
            bottom: 3mm;
        }

        .signature-table {
            table-layout: fixed;
        }

        .signature-table td {
            border: 1.2px solid #000;
            height: 42px;
            vertical-align: top;
            padding: 2px 4px;
            font-size: 4.9pt;
        }

        .signature-head {
            text-align: center;
            font-size: 4.8pt;
            margin-bottom: 25px;
        }

        .signature-info {
            font-size: 4.8pt;
            line-height: 1.25;
        }
    </style>
</head>
<body>
@php
    $officialInvoiceNumber = function (?string $reference) {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return '—';
        }

        if (preg_match('/^(FACT|FAC|F)-20(\d{2})-(\d+)$/', $reference, $matches)) {
            return "N° {$matches[3]}/{$matches[2]}";
        }

        if (preg_match('/^(.+)-20(\d{2})-(\d+)$/', $reference, $matches)) {
            return "N° {$matches[3]}/{$matches[2]}";
        }

        return $reference;
    };

    $blRefs = collect($facture['bl_refs'] ?? [])->values();
    $firstBl = $blRefs->first() ?? '—';
    $dateFacture = $facture['date'] ?? '—';
    $linesCollection = collect($lines ?? [])->values();
    $minRows = 12;
@endphp

<table class="layout">
    <tr>
        @for ($copyIndex = 0; $copyIndex < 2; $copyIndex++)
            <td class="copy-cell">
                <div class="sheet">
                    <table class="top">
                        <tr>
                            <td class="company">
                                <div class="company-name">{{ $company['name'] ?? 'COMPAGNIE MALAGASY DE PLASTIQUE' }}</div>

                                @if (!empty($company['identification']))
                                    <div class="company-line">{{ $company['identification'] }}</div>
                                @endif

                                @if (!empty($company['address']))
                                    <div class="company-line">{{ $company['address'] }}</div>
                                @endif

                                @if (!empty($company['city']))
                                    <div class="company-line">{{ $company['city'] }}</div>
                                @endif

                                @if (!empty($company['phone']) || !empty($company['email']))
                                    <div class="company-line">
                                        {{ $company['phone'] ?? '' }}
                                        @if (!empty($company['phone']) && !empty($company['email'])) - @endif
                                        {{ $company['email'] ?? '' }}
                                    </div>
                                @endif
                            </td>

                            <td class="logo-cell">
                                @if (!empty($company['logo_src']))
                                    <img class="brand-logo" src="{{ $company['logo_src'] }}" alt="CMP">
                                @else
                                    <strong>CMP</strong>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <table class="client-block">
                        <tr>
                            <td class="client-label">Doit :</td>
                            <td class="client-value">{{ $facture['client_nom'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="client-label">NIF</td>
                            <td class="client-value">{{ $facture['client_nif'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="client-label">STAT</td>
                            <td class="client-value">{{ $facture['client_stat'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="client-label">Adresse</td>
                            <td class="client-value">{{ $facture['client_adresse'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="client-label">Interlocuteur</td>
                            <td class="client-value">{{ $facture['client_interlocuteur'] ?? '—' }}</td>
                        </tr>
                    </table>

                    <div class="title-box">
                        FACTURE {{ $officialInvoiceNumber($facture['numero'] ?? '') }}
                    </div>
                    <div class="subtitle">BON DE LIVRAISON - FACTURE</div>

                    <table class="bl-box">
                        <tr>
                            <td class="bl-head">BL</td>
                        </tr>
                        <tr>
                            <td>REF : {{ $firstBl }}</td>
                        </tr>
                        <tr>
                            <td>Date : {{ $dateFacture }}</td>
                        </tr>
                    </table>

                    <table class="lines">
                        <thead>
                            <tr>
                                <th style="width: 42px;">Ref</th>
                                <th>Désignation</th>
                                <th style="width: 42px;">Qté</th>
                                <th style="width: 58px;">PU</th>
                                <th style="width: 75px;">Montant MGA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($index = 0; $index < max($minRows, $linesCollection->count()); $index++)
                                @php
                                    $line = $linesCollection->get($index);
                                @endphp
                                <tr>
                                    <td class="center">{{ $line['reference'] ?? '' }}</td>
                                    <td>{{ $line['designation'] ?? '' }}</td>
                                    <td class="center">{{ $line['quantite'] ?? '' }}</td>
                                    <td class="right">{{ $line['prix_unitaire'] ?? '' }}</td>
                                    <td class="right">{{ $line['montant'] ?? '' }}</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    <table class="totals">
                        <tr>
                            <td class="center" style="width: 35px;">{{ $linesCollection->count() }}</td>
                            <td class="total-label">HT</td>
                            <td class="right">{{ $facture['total_ht'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="total-label">TVA 20%</td>
                            <td class="right">{{ $facture['tva'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="total-label">Net à payer</td>
                            <td class="right">{{ $facture['net_a_payer'] ?? '—' }}</td>
                        </tr>
                    </table>

                    <div class="amount-words">
                        Arrêté la présente facture à la somme de<br>
                        <strong>{{ $facture['montant_en_lettres'] ?? '—' }}</strong>.
                    </div>

                    <div class="payment">
                        Mode de paiement :
                        <strong>
                            @if (($facture['mode_paiement'] ?? '') !== '—')
                                {{ $facture['mode_paiement'] }}
                            @else
                                Par chèque ou virement
                            @endif
                        </strong>
                        <br>
                        à l'ordre de
                        <strong>COMPAGNIE MALAGASY DE PLASTIQUE</strong>
                        <br>
                        Échéance : {{ $facture['echeance_paiement'] ?? '—' }}
                    </div>

                    <div class="date-place">
                        Antananarivo, le&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $dateFacture }}
                    </div>

                    <div class="bottom-fixed">
                        <table class="signature-table">
                            <tr>
                                <td>
                                    <div class="signature-head">CMP</div>
                                    <div class="signature-info">Nom :</div>
                                </td>
                                <td>
                                    <div class="signature-head">TRANSPORTEUR</div>
                                    <div class="signature-info">
                                        Immatriculation N°<br>
                                        Nom :<br>
                                        Date :
                                    </div>
                                </td>
                                <td>
                                    <div class="signature-head">CLIENT</div>
                                    <div class="signature-info">
                                        Nom :<br>
                                        Date :
                                    </div>
                                </td>
                            </tr>
                        </table>
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