<?php
// Configuration (à remplacer par un fichier .env ou des variables d'environnement en production)
$apiKey = getenv('OPENWEATHERMAP_API_KEY') ?: 'XXXXXXXXXXXX'; // Remplace par ta clé API
$defaultCity = isset($_GET['city']) ? filter_var($_GET['city'], FILTER_SANITIZE_STRING) : 'Paris';
$defaultCountry = isset($_GET['country']) ? filter_var($_GET['country'], FILTER_SANITIZE_STRING) : 'FR';

// Liste des villes à afficher dans le tableau
$citiesList = [
    ['name' => 'Marseille', 'country' => 'FR'],
    ['name' => 'Lille', 'country' => 'FR'],
    ['name' => 'Brest', 'country' => 'FR'],
    ['name' => 'Strasbourg', 'country' => 'FR'],
    ['name' => 'Paris', 'country' => 'FR'],
];

// Fonction pour récupérer les données de l'API
function getDataFromApi($url) {
    $response = @file_get_contents($url);
    if ($response === false) {
        throw new Exception("Impossible de récupérer les données depuis l'API.");
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur de décodage JSON : " . json_last_error_msg());
    }
    return $data;
}

// Fonction pour obtenir la météo actuelle d'une ville
function getCurrentWeather($city, $country, $state, $apiKey) {
    $geocodingUrl = $state
        ? "http://api.openweathermap.org/geo/1.0/direct?q={$city},{$state},{$country}&appid={$apiKey}&limit=1"
        : "http://api.openweathermap.org/geo/1.0/direct?q={$city},{$country}&appid={$apiKey}&limit=1";

    $geocodingData = getDataFromApi($geocodingUrl);
    if (empty($geocodingData)) {
        return null;
    }

    $lat = $geocodingData[0]['lat'];
    $lon = $geocodingData[0]['lon'];
    $oneCallUrl = "https://api.openweathermap.org/data/3.0/onecall?lat={$lat}&lon={$lon}&exclude=minutely,hourly,daily,alerts&appid={$apiKey}&units=metric&lang=fr";
    $weatherData = getDataFromApi($oneCallUrl);
    return [
        'city' => $city,
        'temp' => round($weatherData['current']['temp'], 1),
        'description' => $weatherData['current']['weather'][0]['description'],
        'icon' => $weatherData['current']['weather'][0]['icon'],
        'lat' => $lat,
        'lon' => $lon,
    ];
}

// Fonction pour obtenir l'URL de l'icône météo
function getWeatherIconUrl($iconCode) {
    return "http://openweathermap.org/img/wn/{$iconCode}@2x.png";
}

// Fonction pour obtenir la prévision la plus proche d'une heure cible
function getForecastForTime($forecastData, $targetTime, $dayOffset = 0) {
    $targetDateTime = strtotime("+{$dayOffset} days {$targetTime}");
    $closestForecast = null;
    $minDiff = PHP_INT_MAX;
    foreach ($forecastData['hourly'] as $forecast) {
        $diff = abs($forecast['dt'] - $targetDateTime);
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closestForecast = $forecast;
        }
    }
    return $closestForecast;
}

// Récupération des données météo pour la ville sélectionnée
$selectedCityWeather = null;
$geocodingData = null;
if ($defaultCity) {
    try {
        $geocodingUrl = "http://api.openweathermap.org/geo/1.0/direct?q={$defaultCity},{$defaultCountry}&appid={$apiKey}&limit=1";
        $geocodingData = getDataFromApi($geocodingUrl);
        if (!empty($geocodingData)) {
            $lat = $geocodingData[0]['lat'];
            $lon = $geocodingData[0]['lon'];
            $oneCallUrl = "https://api.openweathermap.org/data/3.0/onecall?lat={$lat}&lon={$lon}&exclude=minutely&appid={$apiKey}&units=metric&lang=fr";
            $weatherData = getDataFromApi($oneCallUrl);
            $selectedCityWeather = $weatherData;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération de la météo actuelle pour chaque ville de la liste
$citiesWeather = [];
foreach ($citiesList as $cityItem) {
    $weather = getCurrentWeather($cityItem['name'], $cityItem['country'], '', $apiKey);
    if ($weather) {
        $citiesWeather[] = $weather;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prévisions Météo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-container input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-container button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-container button:hover {
            background-color: #45a049;
        }
        .weather-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .current-weather, .forecast-container, .alerts-container {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }
        .weather-item, .alert-item, .city-item {
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            flex: 1 1 150px;
            min-width: 120px;
            background-color: #f9f9f9;
        }
        .weather-item img, .city-item img {
            width: 60px;
            height: 60px;
        }
        .alert-item {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        h2, h3 {
            color: #333;
        }
        iframe {
            width: 100%;
            max-width: 600px;
            height: 400px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            .weather-item, .alert-item, .city-item {
                flex: 1 1 100%;
            }
        }

    </style>
</head>
<body>

    <h1>Météo en France</h1>

    <!-- Barre de recherche -->
    <div class="search-container">
        <form method="GET" action="">
            <input type="text" name="city" placeholder="Rechercher une ville..." value="<?php echo htmlspecialchars($defaultCity); ?>" required>
            <input type="hidden" name="country" value="FR">
            <button type="submit">Rechercher</button>
        </form>
    </div>

    <!-- Tableau des villes -->
    <h2>Météo actuelle par ville</h2>
    <table>
        <thead>
            <tr>
                <th>Ville</th>
                <th>Température</th>
                <th>Description</th>
                <th>Icône</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citiesWeather as $cityWeather): ?>
                <tr onclick="window.location.href='?city=<?php echo urlencode($cityWeather['city']); ?>&country=FR'">
                    <td><?php echo htmlspecialchars($cityWeather['city']); ?></td>
                    <td><?php echo $cityWeather['temp']; ?>°C</td>
                    <td><?php echo htmlspecialchars($cityWeather['description']); ?></td>
                    <td><img src="<?php echo getWeatherIconUrl($cityWeather['icon']); ?>" alt="<?php echo htmlspecialchars($cityWeather['description']); ?>"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Affichage détaillé pour la ville sélectionnée -->
    <?php if ($selectedCityWeather): ?>
        <div class="weather-container">
            <h2>Météo détaillée pour <?php echo htmlspecialchars($defaultCity); ?></h2>

            <div class="current-weather">
                <div class="weather-item">
                    <h3>Météo actuelle</h3>
                    <p><?php echo date('H:i'); ?></p>
                    <img src="<?php echo getWeatherIconUrl($selectedCityWeather['current']['weather'][0]['icon']); ?>" alt="<?php echo htmlspecialchars($selectedCityWeather['current']['weather'][0]['description']); ?>">
                    <p><?php echo htmlspecialchars($selectedCityWeather['current']['weather'][0]['description']); ?></p>
                    <p>Température: <?php echo round($selectedCityWeather['current']['temp'], 1); ?>°C</p>
                    <p>Ressentie: <?php echo round($selectedCityWeather['current']['feels_like'], 1); ?>°C</p>
                </div>
            </div>

            <h2>Les prochaines heures</h2>
            <div class="forecast-container">
                <?php
                $now = time();
                foreach ($selectedCityWeather['hourly'] as $hourlyForecast) {
                    $forecastTime = $hourlyForecast['dt'];
                    if ($forecastTime > $now +3600 && $forecastTime <= $now + 6 * 3600) {
                        $date = new DateTime("@{$forecastTime}");
                        $iconUrl = getWeatherIconUrl($hourlyForecast['weather'][0]['icon']);
                        echo "<div class='weather-item'>";
                        echo "<h3>{$date->format('H:i')}</h3>";
                        echo "<img src='{$iconUrl}' alt='" . htmlspecialchars($hourlyForecast['weather'][0]['description']) . "'>";
                        echo "<p>" . htmlspecialchars($hourlyForecast['weather'][0]['description']) . "</p>";
                        echo "<p>Température: " . round($hourlyForecast['temp'], 1) . "°C</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>

            <h2>Prévisions pour demain</h2>
            <div class="forecast-container">
                <?php
                $timesTomorrow = ['09:00', '12:00', '17:00'];
                foreach ($timesTomorrow as $time) {
                    $forecast = getForecastForTime($selectedCityWeather, $time, 1);
                    if ($forecast) {
                        $forecastIconUrl = getWeatherIconUrl($forecast['weather'][0]['icon']);
                        echo "<div class='weather-item'>";
                        echo "<h3>Demain<br>{$time}</h3>";
                        echo "<img src='{$forecastIconUrl}' alt='" . htmlspecialchars($forecast['weather'][0]['description']) . "'>";
                        echo "<p>" . htmlspecialchars($forecast['weather'][0]['description']) . "</p>";
                        echo "<p>Température: " . round($forecast['temp'], 1) . "°C</p>";
                        echo "</div>";
                    } else {
                        echo "<div class='weather-item'>";
                        echo "<h3>Demain<br>{$time}</h3>";
                        echo "<p>Prévision non disponible</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>

            <h2>Alerte météo</h2>
            <div class="alerts-container">
                <?php
                if (isset($selectedCityWeather['alerts'])) {
                    foreach ($selectedCityWeather['alerts'] as $alert) {
                        echo "<div class='alert-item'>";
                        echo "<h3>Alerte Météo</h3>";
                        echo "<p><strong>Événement:</strong> " . htmlspecialchars($alert['event']) . "</p>";
                        echo "<p><strong>Début:</strong> " . date('Y-m-d H:i', $alert['start']) . "</p>";
                        echo "<p><strong>Fin:</strong> " . date('Y-m-d H:i', $alert['end']) . "</p>";
                        echo "<p><strong>Description:</strong> " . htmlspecialchars($alert['description']) . "</p>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='alert-item'><p>Aucune alerte météo en cours.</p></div>";
                }
                ?>
            </div>

            <h2>Carte</h2>
            <?php
            function displayMap($lat, $lon) {
                $mapUrl = "https://www.openstreetmap.org/export/embed.html?bbox=" .
                          ($lon - 0.01) . "," . ($lat - 0.01) . "," . ($lon + 0.01) . "," . ($lat + 0.01) .
                          "&layer=mapnik&marker=" . urlencode($lat) . "," . urlencode($lon);
                echo '<iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="' . $mapUrl . '"></iframe>';
            }
            displayMap($geocodingData[0]['lat'], $geocodingData[0]['lon']);
            ?>
        </div>
    <?php elseif (isset($error)): ?>
        <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
</body>
</html>
