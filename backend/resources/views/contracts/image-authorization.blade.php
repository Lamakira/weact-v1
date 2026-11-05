<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Autorisation d'utilisation de l'image {{ $reference }}</title>
    <style>
        @page { margin: 56px 52px 70px 52px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; line-height: 1.55; color: #1f2937; }
        h1 { font-size: 19px; margin: 0 0 4px; color: #111827; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: #198496; text-transform: uppercase; letter-spacing: 0.4px; }
        .ref { color: #6b7280; font-size: 10px; margin: 0 0 14px; }
        table.conditions { width: 100%; border-collapse: collapse; }
        table.conditions td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        table.conditions td.label { width: 28%; background: #f9fafb; font-weight: bold; }
        .article { margin: 0 0 9px; page-break-inside: avoid; }
        .article .title { font-weight: bold; color: #111827; }
        .article ul { margin: 3px 0 0 16px; padding: 0; }
        table.signatures { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-top: 6px; }
        table.signatures td { width: 50%; border: 1px solid #e5e7eb; padding: 10px; vertical-align: top; page-break-inside: avoid; }
        .sig-role { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; }
        .sig-name { font-size: 13px; font-weight: bold; margin: 4px 0; color: #111827; }
        .muted { color: #6b7280; font-size: 9.5px; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="footer">Contrat {{ $reference }} · archivé par WeAct</div>

    <h1>Autorisation d'utilisation de l'image</h1>
    <p class="ref">Référence {{ $reference }}</p>

    <p>
        Entre <strong>{{ $faceName }}</strong>{{ $faceVille ? ', '.$faceVille : '' }} (la « Face »)
        et <strong>{{ $producerName }}</strong> (le « Producteur »).
    </p>

    <h2>Conditions particulières</h2>
    <table class="conditions">
        <tr>
            <td class="label">Projet</td>
            <td>
                @if(! empty($projet['titre']))
                    <strong>{{ $projet['titre'] }}</strong><br>
                @endif
                {{ $projet['type_contenu'] ?? '' }}
                @if(! empty($projet['nom_produit']))
                    : {{ $projet['nom_produit'] }}
                @endif
                @if(! empty($projet['lieu']))
                    <br>Lieu : {{ $projet['lieu'] }}
                @endif
                @if(! empty($projet['date_debut']))
                    <br>Date : {{ \Carbon\Carbon::parse($projet['date_debut'])->format('d/m/Y') }}@if(! empty($projet['date_fin']) && $projet['date_fin'] !== $projet['date_debut']) au {{ \Carbon\Carbon::parse($projet['date_fin'])->format('d/m/Y') }}@endif
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Finalité</td>
            <td>{{ $contract->finalite }}</td>
        </tr>
        <tr>
            <td class="label">Supports</td>
            <td>{{ $supportsLabel }}</td>
        </tr>
        <tr>
            <td class="label">Territoire</td>
            <td>{{ $territoireLabel }}</td>
        </tr>
        <tr>
            <td class="label">Durée</td>
            <td>{{ $contract->duree_annees }} {{ $contract->duree_annees > 1 ? 'ans' : 'an' }} à compter de la prise d'effet</td>
        </tr>
        <tr>
            <td class="label">Contrepartie</td>
            <td>
                @foreach($contrepartieLines as $line)
                    {{ $line }}@if(! $loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label">Prise d'effet</td>
            <td>{{ $effectiveMoment }}</td>
        </tr>
    </table>

    <h2>Conditions générales</h2>
    @foreach($articles as $article)
        <div class="article">
            <span class="title">Article {{ $loop->iteration }} : {{ $article['title'] }}.</span>
            {{ $article['body'] }}
            @if(! empty($article['bullets']))
                <ul>
                    @foreach($article['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach

    <h2>Signatures électroniques</h2>
    <table class="signatures">
        <tr>
            <td>
                <div class="sig-role">Le Producteur</div>
                <div class="sig-name">{{ $contract->producer_signed_name }}</div>
                <div class="muted">
                    Signé le {{ $contract->producer_signed_at->timezone(config('app.business_timezone'))->format('d/m/Y à H:i') }}<br>
                    Code reçu à {{ $contract->producer_signed_email_masked }}
                </div>
            </td>
            <td>
                <div class="sig-role">La Face</div>
                <div class="sig-name">{{ $contract->face_signed_name }}</div>
                <div class="muted">
                    Signé le {{ $contract->face_signed_at?->timezone(config('app.business_timezone'))->format('d/m/Y à H:i') }}<br>
                    Code reçu à {{ $contract->face_signed_email_masked }}
                    @if($contract->guardian_name)
                        <br><br>
                        Face mineure, signature du parent ou tuteur :<br>
                        <strong>{{ $contract->guardian_name }}</strong>
                        ({{ ['mere' => 'mère', 'pere' => 'père', 'tuteur' => 'tuteur'][$contract->guardian_relation] ?? $contract->guardian_relation }})
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
