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
	'ho-oh' => 'Ho-Oh',
	'deoxys-normal' => 'Deoxys',
	'wormadam-plant' => 'Wormadam',
	'mime-jr' => 'Mime Jr.',
	'porygon-z' => 'Porygon-Z',
	'giratina-altered' => 'Giratina',
	'shaymin-land' => 'Shaymin',
	'basculin-red-striped' => 'Basculin',
	'darmanitan-standard' => 'Darmanitan',
	'tornadus-incarnate' => 'Tornadus',
	'thundurus-incarnate' => 'Thundurus',
	'landorus-incarnate' => 'Landorus',
	'keldeo-ordinary' => 'Keldeo',
	'meloetta-aria' => 'Meloetta',
	'meowstic-male' => 'Meowstic',
	'aegislash-shield' => 'Aegislash',
	'pumpkaboo-average' => 'Pumpkaboo',
	'gourgeist-average' => 'Gourgeist',
];

function pokeRename($id, $name) {
	global $pokeRenames;
	
	if (isset($pokeRenames[$name])) {
		return $pokeRenames[$name];
	}
	
	if ($id > 10000) {
		// This is a mega evolution. Remove the mega and replace it with M at the start
		$name = 'M-' . ucfirst(str_replace('-mega', '', $name));
		// If this is an X or Y mega evolution, replace the dash with a space and uppercase the character
		$name = str_replace('-x', ' X', $name);
		$name = str_replace('-y', ' Y', $name);
	}
	
	return ucfirst($name);
}

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

$i = 0;
while (true) {
	$i++;
	if ($i == 722) {
		// After 721, jump to 10001
		$i = 10001;
	}
	if ($i > 10090) {
		// Stop after 10090
		break;
	}
	
	$json = file_get_contents("$i.json");
	
	$poke = json_decode($json, true);
	
	if ($i > 10000 && strpos($poke['name'], 'mega') === false) {
		// In the special forms, skip non-mega-evolutions
		continue;
	}
	
	$entry = [];
	
	$pokeName = pokeRename($i, $poke['name']);
	$entry['pokemon'] = [['Pokemon' => $pokeName]];
	
	$stats = [];
	if (!preg_match('@([0-9]+)/?$@', $poke['species']['url'], $matches)) {
		die("Unable to extra speciesId from pokemon with id {$poke['id']}!");
	}
	$speciesId = $matches[1];
	if (!isset($speciesData[$speciesId])) {
		die("No species data for pokemon with id {$poke['id']} and speciesId $speciesId!");
	}
	$stats['catch rate'] = $speciesData[$speciesId]['catch rate'];
	$stats['growth rate'] = $growthRates[$speciesData[$speciesId]['growth rate']];
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
		$stats[$toStat] = (string) $stat;
	}
	$types = [];
	$pokeTypes = $poke['types'];
	usort($pokeTypes, function($a, $b) { return $a['slot'] - $b['slot']; });
	foreach ($pokeTypes as $pokeType) {
		$types[] = ucfirst($pokeType['type']['name']);
	}
	$stats['types'] = $types;
	$entry['stats'] = [$stats];
	
	$entry['exp'] = [['base exp' => (string) $poke['base_experience']]];
	
	if ($i < 650) {
		// Generation 1-5 sprites are hotlinked from pokemondb
		$imageName = $imageRenames[$poke['name']] ?: $poke['name'];
		$entry['images'] = [
			'normal' => [
				'front' => 'https://img.pokemondb.net/sprites/black-white/anim/normal/' . $imageName . '.gif',
				'back' => 'https://img.pokemondb.net/sprites/black-white/anim/back-normal/' . $imageName . '.gif',
			],
			'shiny' => [
				'front' => 'https://img.pokemondb.net/sprites/black-white/anim/shiny/' . $imageName . '.gif',
				'back' => 'https://img.pokemondb.net/sprites/black-white/anim/back-shiny/' . $imageName . '.gif',
			],
		];
	} elseif ($i < 10000) {
		// Generation 6 sprites are hosted with the game
		$imageName = sprintf('%03d', $speciesId) . ".png";
		$entry['images'] = [
			'normal' => [
				'front' => 'sprites/' . $imageName,
				'back' => 'sprites/back/' . $imageName,
			],
			'shiny' => [
				'front' => 'sprites/s' . $imageName,
				'back' => 'sprites/back/s' . $imageName,
			],
		];
	} else {
		// Mega evolution sprites are hosted with the game
		$xy = substr($poke['name'], -2);
		if ($xy === '-x') {
			$xy = 'x';
		} elseif ($xy === '-y') {
			$xy = 'y';
		} else {
			$xy = '';
		}
		$imageName = sprintf('%03d', $speciesId) . "m$xy.png";
		$entry['images'] = [
			'normal' => [
				'front' => 'sprites/' . $imageName,
				'back' => 'sprites/back/' . $imageName,
			],
			'shiny' => [
				'front' => 'sprites/s' . $imageName,
				'back' => 'sprites/back/s' . $imageName,
			],
		];
	}
	
	$pokedex[] = $entry;
}

echo 'const POKEDEX = ' . json_encode($pokedex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n";
