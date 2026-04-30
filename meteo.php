<?php
// Configuration
$apiKey = getenv('OPENWEATHERMAP_API_KEY') ?: 'MY_KEY';
$defaultCity = isset($_GET['city']) ? filter_var($_GET['city'], FILTER_SANITIZE_STRING) : 'Saint-Herblain';
$defaultCountry = isset($_GET['country']) ? filter_var($_GET['country'], FILTER_SANITIZE_STRING) : 'FR';
$selectedLat = isset($_GET['lat']) ? filter_var($_GET['lat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
$selectedLon = isset($_GET['lon']) ? filter_var($_GET['lon'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;

// Liste des villes à afficher dans le tableau
$citiesList = [
    ['name' => 'Saint-Herblain', 'country' => 'FR'],
    ['name' => 'Bordeaux', 'country' => 'FR'],
    ['name' => 'Pouldreuzic', 'country' => 'FR'],
    ['name' => 'Saint-Denoeux', 'country' => 'FR'],
    ['name' => 'Blois', 'country' => 'FR'],
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

// Fonction pour obtenir les résultats de géocodage (plusieurs résultats possibles)
function getGeocodingResults($city, $country, $apiKey) {
    $geocodingUrl = "http://api.openweathermap.org/geo/1.0/direct?q={$city},{$country}&appid={$apiKey}&limit=5";
    return getDataFromApi($geocodingUrl);
}

// Fonction pour obtenir la météo actuelle d'une ville
function getCurrentWeather($city, $country, $state, $apiKey) {
    $geocodingData = getGeocodingResults($city, $country, $apiKey);
    if (empty($geocodingData)) {
        return null;
    }
    $lat = $geocodingData[0]['lat'];
    $lon = $geocodingData[0]['lon'];
    $oneCallUrl = "https://api.openweathermap.org/data/3.0/onecall?lat={$lat}&lon={$lon}&exclude=minutely,hourly,daily,alerts&appid={$apiKey}&units=metric&lang=fr";
    $weatherData = getDataFromApi($oneCallUrl);
    return [
        'city' => $city,
        'country' => $country,
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

// Récupération des résultats de géocodage pour la ville recherchée
$geocodingResults = [];
$selectedCityWeather = null;
$error = null;

if ($defaultCity && !$selectedLat && !$selectedLon) {
    try {
        $geocodingResults = getGeocodingResults($defaultCity, $defaultCountry, $apiKey);
        if (count($geocodingResults) === 1) {
            // Une seule ville trouvée : rediriger vers l'URL avec lat/lon
            $lat = $geocodingResults[0]['lat'];
            $lon = $geocodingResults[0]['lon'];
            header("Location: ?city=" . urlencode($defaultCity) . "&country=" . urlencode($defaultCountry) . "&lat={$lat}&lon={$lon}");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Si lat/lon sont fournis, récupérer la météo pour ces coordonnées
if ($selectedLat && $selectedLon) {
    try {
        $oneCallUrl = "https://api.openweathermap.org/data/3.0/onecall?lat={$selectedLat}&lon={$selectedLon}&exclude=minutely&appid={$apiKey}&units=metric&lang=fr";
        $selectedCityWeather = getDataFromApi($oneCallUrl);
        $selectedCityWeather['lat'] = $selectedLat;
        $selectedCityWeather['lon'] = $selectedLon;
        // Récupérer le nom de la ville à partir des coordonnées
        $reverseGeocodingUrl = "http://api.openweathermap.org/geo/1.0/reverse?lat={$selectedLat}&lon={$selectedLon}&appid={$apiKey}&limit=1";
        $reverseGeocodingData = getDataFromApi($reverseGeocodingUrl);
        if (!empty($reverseGeocodingData)) {
            $defaultCity = $reverseGeocodingData[0]['name'];
            $defaultCountry = $reverseGeocodingData[0]['country'];
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
        .city-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .city-item-name {
            font-weight: bold;
            font-size: 1.1em;
        }
        .city-item-temp {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
        }
        .city-item-icon {
            width: 50px;
            height: 50px;
            align-self: flex-end;
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
        .cities-weather-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        .homonyms-container {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f0f8ff;
        }
        .homonyms-container h3 {
            margin-top: 0;
        }
        .homonym-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .homonym-item {
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .homonym-item:hover {
            background-color: #e6f7ff;
        }
        .homonym-item a {
            text-decoration: none;
            color: #333;
            display: block;
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

    <!-- Affichage des homonymes si plusieurs résultats -->
    <?php if (!empty($geocodingResults) && count($geocodingResults) > 1 && !$selectedLat): ?>
        <div class="homonyms-container">
            <h3>Plusieurs villes correspondent à "<?php echo htmlspecialchars($defaultCity); ?>". Veuillez choisir :</h3>
            <div class="homonym-list">
                <?php foreach ($geocodingResults as $result): ?>
                    <div class="homonym-item">
                        <a href="?city=<?php echo urlencode($result['name']); ?>&country=<?php echo urlencode($result['country']); ?>&lat=<?php echo $result['lat']; ?>&lon=<?php echo $result['lon']; ?>">
                            <?php
                            echo htmlspecialchars($result['name']);
                            if (isset($result['state'])) {
                                echo ", " . htmlspecialchars($result['state']);
                            }
                            echo ", " . htmlspecialchars($result['country']);
                            ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <h2>Météo actuelle par ville</h2>
    <div class="cities-weather-container">
        <?php foreach ($citiesWeather as $cityWeather): ?>
            <div class="city-item" onclick="window.location.href='?city=<?php echo urlencode($cityWeather['city']); ?>&country=<?php echo urlencode($cityWeather['country']); ?>&lat=<?php echo $cityWeather['lat']; ?>&lon=<?php echo $cityWeather['lon']; ?>'">
                <div class="city-item-header">
                    <div class="city-item-name"><?php echo htmlspecialchars($cityWeather['city']); ?></div>
                    <img class="city-item-icon" src="<?php echo getWeatherIconUrl($cityWeather['icon']); ?>" alt="<?php echo htmlspecialchars($cityWeather['description']); ?>">
                </div>
                <div class="city-item-temp"><?php echo $cityWeather['temp']; ?>°C</div>
                <div><?php echo htmlspecialchars($cityWeather['description']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Affichage détaillé pour la ville sélectionnée -->
    <?php if ($selectedCityWeather): ?>
        <div class="weather-container">
            <h2><?php echo htmlspecialchars($defaultCity); ?></h2>

            <?php
            if (isset($selectedCityWeather['alerts'])) {
                foreach ($selectedCityWeather['alerts'] as $alert) {
                    echo "<div class='alerts-container'>";
                    echo "<div class='alert-item'>";
                    echo "<h3>Alerte Météo</h3>";
                    echo "<p><strong>" . htmlspecialchars($alert['event']) . "</strong></p>";
                    echo "<p><strong>Du </strong> " . date('d/m/Y H:i', $alert['start']);
                    echo "<strong> au </strong> " . date('d/m/Y H:i', $alert['end']) . "</p>";
                    echo "<p>".htmlspecialchars($alert['description'])."</p>";
                    echo "</div>";
                    echo "</div>";
                }
            }
            ?>

            <div class="cities-weather-container">
                <?php
                $now = time();
                foreach ($selectedCityWeather['hourly'] as $hourlyForecast) {
                    $forecastTime = $hourlyForecast['dt'];
                    if ($forecastTime > ($now+3600) && $forecastTime <= $now+6*3600) {
                        $date = new DateTime("@{$forecastTime}");
                        $iconUrl = getWeatherIconUrl($hourlyForecast['weather'][0]['icon']);
                        echo "<div class='city-item'>";
                        echo "<div class='city-item-header'>";
                        echo "<div class='city-item-name'>{$date->format('H:i')}</div>";
                        echo "<img class='city-item-icon' src='{$iconUrl}' alt='" . htmlspecialchars($hourlyForecast['weather'][0]['description']) . "'>";
                        echo "</div>";
                        echo "<div class='city-item-temp'>" . round($hourlyForecast['temp'], 1) . "°C</div>";
                        echo "</div>";
                    }
                }
                ?>
            </div>

            <div class="forecast-container">
                <?php
                $timesTomorrow = ['08:00', '10:00', '13:00', '17:00', '20:00'];
                foreach ($timesTomorrow as $time) {
                    $forecast = getForecastForTime($selectedCityWeather, $time, 1);
                    if ($forecast) {
                        $forecastIconUrl = getWeatherIconUrl($forecast['weather'][0]['icon']);
                        echo "<div class='city-item'>";
                        echo "<div class='city-item-header'>";
                        echo "<div class='city-item-name'>Demain<br>{$time}</div>";
                        echo "<img class='city-item-icon'src='{$forecastIconUrl}' alt='" . htmlspecialchars($forecast['weather'][0]['description']) . "'>";
                        echo "</div>";
                        echo "<div class='city-item-temp'>";
                        echo round($forecast['temp'], 1) . "°C";
                        echo "</div>";
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

            <h2>Carte</h2>
            <?php
            function displayMap($lat, $lon) {
                $mapUrl = "https://www.openstreetmap.org/export/embed.html?bbox=" .
                          ($lon - 0.01) . "," . ($lat - 0.01) . "," . ($lon + 0.01) . "," . ($lat + 0.01) .
                          "&layer=mapnik&marker=" . urlencode($lat) . "," . urlencode($lon);
                echo '<iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="' . $mapUrl . '"></iframe>';
            }
            displayMap($selectedLat, $selectedLon);
            ?>
        </div>
    <?php elseif (isset($error)): ?>
        <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
</body>
</html>