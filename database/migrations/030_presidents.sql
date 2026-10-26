-- =====================================================================
-- FIUCC Portal — migration 030 : présidents de la RDC modifiables depuis
-- le back office (/admin/presidents), au lieu de config/presidents.php.
--
-- Le bureau ajoute un président le jour d'une passation, sans
-- développeur. Pas de mise à jour automatique depuis une source externe
-- (Wikidata...) : modifiable par n'importe qui, elle afficherait sans
-- contrôle un nom ou une photo faux sur le site officiel, au moment même
-- où la page compte (élection contestée).
--
-- photo : chemin web. Les cinq premiers portraits sont livrés avec le
-- code (/assets/images/presidents/) ; un portrait envoyé par l'admin va
-- sous /uploads/presidents/.
-- credit_license : seules des licences permettant la réutilisation ;
-- 'permission' = autorisation écrite de l'auteur ou de son ayant droit.
-- role_en / bio_en facultatifs : sans traduction, le français s'affiche.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE presidents (
    id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)      NOT NULL,
    role            VARCHAR(200)      NOT NULL,
    role_en         VARCHAR(200)      NULL,
    bio             TEXT              NOT NULL,
    bio_en          TEXT              NULL,
    from_year       SMALLINT UNSIGNED NOT NULL,
    until_year      SMALLINT UNSIGNED NULL,
    photo           VARCHAR(255)      NULL,
    credit_author   VARCHAR(200)      NULL,
    credit_license  ENUM('public_domain','cc0','cc_by_4','cc_by_sa_4','permission') NULL,
    credit_source   VARCHAR(500)      NULL,
    is_published    TINYINT(1)        NOT NULL DEFAULT 1,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_presidents_published_from (is_published, from_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les cinq présidents jusqu'ici dans config/presidents.php, textes repris
-- de resources/lang à l'identique.
INSERT INTO presidents
    (name, role, role_en, bio, bio_en, from_year, until_year, photo, credit_author, credit_license, credit_source)
VALUES
    ('Joseph Kasa-Vubu',
     'Président de la République du Congo',
     'President of the Republic of the Congo',
     'Premier président du Congo indépendant, élu par le Parlement en juin 1960. Figure de l''ABAKO et de la lutte pour l''indépendance, il est écarté par le coup d''État du 24 novembre 1965.',
     'First president of the independent Congo, elected by Parliament in June 1960. A leading figure of ABAKO and of the independence struggle, he was removed by the coup of 24 November 1965.',
     1960, 1965, '/assets/images/presidents/kasa-vubu.jpg',
     'Anefo / Nationaal Archief', 'cc0',
     'https://commons.wikimedia.org/wiki/File:Joseph_Kasa-Vubu_at_the_Belgo-Congolese_Round_Table_Conference.jpg'),
    ('Mobutu Sese Seko',
     'Président de la République du Congo, puis du Zaïre',
     'President of the Republic of the Congo, then of Zaire',
     'Arrivé au pouvoir par le coup d''État de 1965, il renomme le pays Zaïre en 1971 et gouverne sous un régime de parti unique pendant plus de trente ans, jusqu''à l''entrée de l''AFDL à Kinshasa en mai 1997.',
     'Having taken power in the 1965 coup, he renamed the country Zaire in 1971 and ruled under a one-party regime for over thirty years, until the AFDL entered Kinshasa in May 1997.',
     1965, 1997, '/assets/images/presidents/mobutu.jpg',
     'Frank Hall', 'public_domain',
     'https://commons.wikimedia.org/wiki/File:Mobutu_(cropped).jpg'),
    ('Laurent-Désiré Kabila',
     'Président de la République démocratique du Congo',
     'President of the Democratic Republic of the Congo',
     'Chef de l''AFDL, il renverse Mobutu en mai 1997 et rend au pays le nom de République démocratique du Congo. Il est assassiné à Kinshasa le 16 janvier 2001.',
     'Leader of the AFDL, he overthrew Mobutu in May 1997 and restored the name Democratic Republic of the Congo. He was assassinated in Kinshasa on 16 January 2001.',
     1997, 2001, '/assets/images/presidents/laurent-desire-kabila.jpg',
     'Etienne Scholasse, European Communities', 'cc_by_4',
     'https://commons.wikimedia.org/wiki/File:Visit_of_Laurent_D%C3%A9sir%C3%A9_Kabila,_President_of_the_Democratic_Republic_of_Congo,_to_the_EC_(cropped2).jpg'),
    ('Joseph Kabila Kabange',
     'Président de la République démocratique du Congo',
     'President of the Democratic Republic of the Congo',
     'Il succède à son père en janvier 2001. Élu en 2006, lors des premières élections pluralistes depuis plus de quarante ans, il est réélu en 2011.',
     'He succeeded his father in January 2001. Elected in 2006, in the first multi-party elections in more than forty years, he was re-elected in 2011.',
     2001, 2019, '/assets/images/presidents/joseph-kabila.jpg',
     'U.S. Department of State', 'public_domain',
     'https://commons.wikimedia.org/wiki/File:Joseph_Kabila_April_2016.jpg'),
    ('Félix Tshisekedi Tshilombo',
     'Président de la République démocratique du Congo',
     'President of the Democratic Republic of the Congo',
     'Président de l''UDPS, il est élu en décembre 2018 et investi le 24 janvier 2019, première passation pacifique du pouvoir depuis l''indépendance. Il est réélu en décembre 2023.',
     'Leader of the UDPS, he was elected in December 2018 and sworn in on 24 January 2019, the first peaceful transfer of power since independence. He was re-elected in December 2023.',
     2019, NULL, '/assets/images/presidents/tshisekedi.jpg',
     'U.S. Department of State', 'public_domain',
     'https://commons.wikimedia.org/wiki/File:Felix_Tshisekedi,_President_of_the_Democratic_Republic_of_the_Congo_(55078564106)_(cropped).jpg');
