<?php

// The stat mapping from pokeapi format to pokeidle format
$statMapping = [
	'hp' => 'hp',
	'attack' => 'attack',
	'defense' => 'defense',
	'special-attack' => 'sp atk',
	'special-defense' => 'sp def',
	'speed' => 'speed',
];

// The growth rates mapping from pokeapi to pokeidle format
$growthRates = [
	'1' => 'Slow',
	'2' => 'Medium Fast',
	'3' => 'Fast',
	'4' => 'Medium Slow',
	'5' => 'Slow then very Fast',
	'6' => 'Fast then very Slow',
];

// Some pokemon have different names in pokeapi than in pokeidle
$pokeRenames = [
	'nidoran-f' => 'Nidoran f',
	'nidoran-m' => 'Nidoran m',
	'mr-mime' => 'Mr. Mime',
];

// Some images have different names on pokemondb than their respective pokemon
$imageRenames = [
	'darmanitan-standard' => 'darmanitan-standard-mode',
];

// Load the catch rate and growth rate from pokemon_species.csv
$speciesData = [];
$firstRow = true;
if (($handle = fopen('pokemon_species.csv', 'r')) !== FALSE) {
    while (($data = fgetcsv($handle)) !== FALSE) {
		if ($firstRow) {
			// Skip the index row
			$firstRow = false;
			continue;
		}
        $speciesData[$data[0]] = [
			'catch rate' => $data[9],
			'growth rate' => $data[14],
		];
    }
    fclose($handle);
} else {
	die('Unable to load species csv!');
}

$pokedex = [];

//for ($i = 1; $i <= 721; $i++) {
for ($i = 1; $i <= 151; $i++) {
	$json = file_get_contents("$i.json");
	
	$poke = json_decode($json, true);
	
	$entry = [];
	
	$pokeName = $pokeRenames[$poke['name']] ?: ucfirst($poke['name']);
	$entry['pokemon'] = [['Pokemon' => $pokeName]];
	
	$stats = [];
	if (!isset($speciesData[$poke['id']])) {
		die("No species data for pokemon with id {$poke['id']}!");
	}
	$stats['catch rate'] = $speciesData[$poke['id']]['catch rate'];
	$stats['growth rate'] = $growthRates[$speciesData[$poke['id']]['growth rate']];
	foreach ($statMapping as $fromStat => $toStat) {
		$stat = null;
		foreach ($poke['stats'] as $pokeStat) {
			if ($pokeStat['stat']['name'] === $fromStat) {
				$stat = $pokeStat['base_stat'];
				break;
			}
		}
		if (is_null($stat)) {
			die("Unable to find stat $fromStat on pokemon {$poke['name']}!");
		}
		$stats[$toStat] = $stat;
	}
	$types = [];
	$pokeTypes = $poke['types'];
	usort($pokeTypes, function($a, $b) { return $a['slot'] - $b['slot']; });
	foreach ($pokeTypes as $pokeType) {
		$types[] = ucfirst($pokeType['type']['name']);
	}
	$stats['types'] = $types;
	$entry['stats'] = [$stats];
	
	$entry['exp'] = [['base exp' => $poke['base_experience']]];
	
	$imageName = $imageRenames[$poke['name']] ?: $poke['name'];
	$entry['images'] = [
		'front' => 'https://img.pokemondb.net/sprites/black-white/anim/normal/' . $imageName . '.gif',
		'back' => 'https://img.pokemondb.net/sprites/black-white/anim/back-normal/' . $imageName . '.gif',
	];
	
	$pokedex[] = $entry;
}

echo 'const POKEDEX = ' . json_encode($pokedex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ';';
