<?php
/**
 * ======================================================================
 * SEPARADOR DE NOMBRES Y APELLIDOS - CON LIMPIEZA COMPLETA
 * ======================================================================
 * 
 * Propósito: Separar campo nombre en nombre + apellido para personas naturales
 *   • Elimina TODOS los caracteres especiales, números y puntuación
 *   • Normaliza a formato estándar (solo letras y espacios)
 *   • Detecta sexo basado en primer nombre
 *   • Asigna tratamiento correspondiente
 *   • Genera CSV para revisión manual
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  SEPARADOR DE NOMBRES Y APELLIDOS - LIMPIEZA COMPLETA           ║\n";
echo "║  (Elimina caracteres especiales, números, puntuación)            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// FUNCIONES DE LIMPIEZA Y NORMALIZACIÓN
// ======================================================================

/**
 * Limpia nombre completo: elimina todo excepto letras y espacios
 */
function limpiarNombreCompleto($text) {
    if (empty($text)) return '';
    
    // Convertir a mayúsculas
    $text = strtoupper(trim($text));
    
    // Eliminar acentos y caracteres especiales unicode
    $text = preg_replace('/[\x{00C0}-\x{00C5}]/u', 'A', $text); // ÀÁÂÃÄÅ
    $text = preg_replace('/[\x{00C6}]/u', 'AE', $text); // Æ
    $text = preg_replace('/[\x{00C7}]/u', 'C', $text); // Ç
    $text = preg_replace('/[\x{00C8}-\x{00CB}]/u', 'E', $text); // ÈÉÊË
    $text = preg_replace('/[\x{00CC}-\x{00CF}]/u', 'I', $text); // ÌÍÎÏ
    $text = preg_replace('/[\x{00D1}]/u', 'N', $text); // Ñ
    $text = preg_replace('/[\x{00D2}-\x{00D6}\x{00D8}]/u', 'O', $text); // ÒÓÔÕÖØ
    $text = preg_replace('/[\x{00D9}-\x{00DC}]/u', 'U', $text); // ÙÚÛÜ
    $text = preg_replace('/[\x{00DD}]/u', 'Y', $text); // Ý
    $text = preg_replace('/[\x{00DF}]/u', 'SS', $text); // ß
    $text = preg_replace('/[\x{00E0}-\x{00E5}]/u', 'A', $text); // àáâãäå
    $text = preg_replace('/[\x{00E6}]/u', 'AE', $text); // æ
    $text = preg_replace('/[\x{00E7}]/u', 'C', $text); // ç
    $text = preg_replace('/[\x{00E8}-\x{00EB}]/u', 'E', $text); // èéêë
    $text = preg_replace('/[\x{00EC}-\x{00EF}]/u', 'I', $text); // ìíîï
    $text = preg_replace('/[\x{00F1}]/u', 'N', $text); // ñ
    $text = preg_replace('/[\x{00F2}-\x{00F6}\x{00F8}]/u', 'O', $text); // òóôõöø
    $text = preg_replace('/[\x{00F9}-\x{00FC}]/u', 'U', $text); // ùúûü
    $text = preg_replace('/[\x{00FD}\x{00FF}]/u', 'Y', $text); // ýÿ
    
    // Eliminar TODOS los caracteres NO alfabéticos (números, puntuación, símbolos)
    $text = preg_replace('/[^A-Z\s]/', ' ', $text);
    
    // Normalizar espacios múltiples
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Separa nombre completo en primer nombre y apellido(s)
 */
function separarNombreCompleto($nombre_completo) {
    $palabras = array_filter(explode(' ', $nombre_completo));
    
    if (count($palabras) < 2) {
        return [
            'nombre' => $nombre_completo,
            'apellido' => NULL,
            'completo' => false
        ];
    }
    
    $primer_nombre = array_shift($palabras);
    $apellido = implode(' ', $palabras);
    
    return [
        'nombre' => $primer_nombre,
        'apellido' => $apellido,
        'completo' => true
    ];
}

// ======================================================================
// LISTAS DE NOMBRES POR SEXO (EXTENDIDAS)
// ======================================================================

$nombres_masculinos = [
    // Nombres masculinos comunes en inglés y español
    'AARON', 'ABRAHAM', 'ADAM', 'ADRIAN', 'ALAN', 'ALBERT', 'ALBERTO', 'ALEJANDRO',
    'ALEX', 'ALEXANDER', 'ALFONSO', 'ALFREDO', 'ALVARO', 'AMADO', 'AMANDO', 'AMBROSIO',
    'AMOS', 'ANDERSON', 'ANDRE', 'ANDRES', 'ANDREW', 'ANGEL', 'ANTHONY', 'ANTON',
    'ANTONIO', 'ARIEL', 'ARMANDO', 'ARNOLD', 'ARTHUR', 'ARTURO', 'AUGUST', 'AUGUSTO',
    'AUSTIN', 'BEN', 'BENJAMIN', 'BERNARD', 'BERNARDO', 'BILL', 'BOB', 'BOBBY',
    'BRAD', 'BRADLEY', 'BRANDON', 'BRENDAN', 'BRIAN', 'BRUCE', 'BRUNO', 'BRYAN',
    'BYRON', 'CALEB', 'CARL', 'CARLOS', 'CARMEL', 'CARMINE', 'CARSON', 'CARTER',
    'CESAR', 'CHAD', 'CHARLES', 'CHARLIE', 'CHASE', 'CHESTER', 'CHRIS', 'CHRISTIAN',
    'CHRISTOPHER', 'CLARK', 'CLAUDE', 'CLAYTON', 'CLIFFORD', 'CLINT', 'CLINTON',
    'COLE', 'COLIN', 'CONNIE', 'CONRAD', 'COREY', 'CORY', 'CRAIG', 'CURTIS',
    'CYRIL', 'CYRUS', 'DALLAS', 'DAMIAN', 'DAMIEN', 'DAMON', 'DAN', 'DANA',
    'DANIEL', 'DANNY', 'DANTE', 'DARIN', 'DARIUS', 'DARREN', 'DARRYL', 'DARYL',
    'DAVE', 'DAVID', 'DEAN', 'DEANDRE', 'DEBORAH', 'DELBERT', 'DENIS', 'DENNY',
    'DENVER', 'DEWEY', 'DICK', 'DIEGO', 'DOMINGO', 'DOMINIC', 'DOMINICK', 'DON',
    'DONALD', 'DONNIE', 'DONOVAN', 'DORIAN', 'DORSEY', 'DOUG', 'DOUGLAS', 'DUANE',
    'DUDLEY', 'DUNCAN', 'DUSTIN', 'DWIGHT', 'DYLAN', 'EARL', 'EARNEST', 'ED',
    'EDDIE', 'EDGAR', 'EDMUND', 'EDUARDO', 'EDWARD', 'EDWIN', 'EFRAIN', 'EFREN',
    'ELBERT', 'ELIAS', 'ELIJAH', 'ELISEO', 'ELLIOT', 'ELLIOTT', 'ELMER', 'ELTON',
    'ELVIS', 'EMANUEL', 'EMIL', 'EMILE', 'EMILIO', 'EMMANUEL', 'ENRIQUE', 'ERIC',
    'ERICK', 'ERIK', 'ERNEST', 'ERNESTO', 'ERROL', 'ERVIN', 'ERWIN', 'ESTEBAN',
    'ETHAN', 'EUGENE', 'EUSEBIO', 'EVAN', 'EVERETT', 'FABIAN', 'FAUSTINO', 'FAUSTO',
    'FEDERICO', 'FELIPE', 'FELIX', 'FERDINAND', 'FERMIN', 'FERNANDO', 'FRAN', 'FRANCIS',
    'FRANCISCO', 'FRANK', 'FRANKLIN', 'FRANKY', 'FRED', 'FREDDIE', 'FREDDY', 'FREDERICK',
    'FREDRICK', 'GABRIEL', 'GAIL', 'GARRET', 'GARRETT', 'GARRY', 'GARTH', 'GARY',
    'GASTON', 'GAVIN', 'GENARO', 'GENE', 'GEOFFREY', 'GEORGE', 'GERALD', 'GERARDO',
    'GERMAN', 'GIL', 'GILBERT', 'GILBERTO', 'GLEN', 'GLENN', 'GONZALO', 'GORDON',
    'GRANT', 'GREG', 'GREGG', 'GREGORY', 'GROVER', 'GUADALUPE', 'GUILLERMO', 'GUS',
    'GUSTAVO', 'HAROLD', 'HARRISON', 'HARRY', 'HARVEY', 'HEATH', 'HECTOR', 'HENRY',
    'HERBERT', 'HERIBERTO', 'HERMAN', 'HILARIO', 'HOMER', 'HORACE', 'HORACIO', 'HOUSTON',
    'HOWARD', 'HUBERT', 'HUGO', 'HUMBERTO', 'IAN', 'IGNACIO', 'IKE', 'IRA',
    'IRVING', 'IRWIN', 'ISAAC', 'ISAIAS', 'ISEA', 'ISIDRO', 'ISMAEL', 'JACK',
    'JACKIE', 'JACKSON', 'JACOB', 'JAKE', 'JAME', 'JAMES', 'JAMIE', 'JAN',
    'JARED', 'JARVIS', 'JASON', 'JAVIER', 'JAY', 'JEAN', 'JED', 'JEFF',
    'JEFFERY', 'JEFFREY', 'JEREMIAH', 'JEREMY', 'JEROME', 'JERRY', 'JESSE', 'JESSIE',
    'JESUS', 'JIM', 'JIMMIE', 'JIMMY', 'JOAN', 'JOAQUIN', 'JODI', 'JODY',
    'JOE', 'JOEL', 'JOEY', 'JOHANN', 'JOHANNE', 'JOHN', 'JOHNNIE', 'JOHNNY',
    'JON', 'JONAS', 'JONATHAN', 'JORDAN', 'JORGE', 'JOSE', 'JOSEPH', 'JOSH',
    'JOSHUA', 'JUAN', 'JUDE', 'JULIAN', 'JULIO', 'JUSTIN', 'KARL', 'KEITH',
    'KELLEY', 'KELLY', 'KEN', 'KENDALL', 'KENNETH', 'KENNY', 'KENT', 'KERMIT',
    'KEVIN', 'KIM', 'KIRK', 'KURT', 'KYLE', 'LANE', 'LARRY', 'LAURENCE',
    'LAURIE', 'LEN', 'LENNY', 'LEO', 'LEON', 'LEONARD', 'LEONARDO', 'LEONEL',
    'LEOPOLDO', 'LESTER', 'LEVY', 'LEWIS', 'LINCOLN', 'LINDA', 'LINDSAY', 'LIONEL',
    'LLOYD', 'LOGAN', 'LONNIE', 'LORAN', 'LOREN', 'LORENZO', 'LOU', 'LOUIS',
    'LOWELL', 'LOYD', 'LUCAS', 'LUIS', 'LUKE', 'LUTHER', 'LYLE', 'LYMAN',
    'LYNN', 'MAC', 'MACK', 'MALCOLM', 'MANUEL', 'MARC', 'MARCEL', 'MARCO',
    'MARCOS', 'MARCUS', 'MARIO', 'MARION', 'MARK', 'MARLON', 'MARSHALL', 'MARTIN',
    'MARTY', 'MARVIN', 'MASON', 'MATHEW', 'MATT', 'MATTHEW', 'MAURICE', 'MAURICIO',
    'MAX', 'MAXIMO', 'MAYNARD', 'MEL', 'MICHAEL', 'MIGUEL', 'MIKE', 'MILAN',
    'MILES', 'MILTON', 'MITCHEL', 'MITCHELL', 'MOHAMMAD', 'MOISES', 'MONROE', 'MONTY',
    'MORGAN', 'MORRIS', 'MOSES', 'NATHAN', 'NATHANIEL', 'NEAL', 'NEIL', 'NELSON',
    'NESTOR', 'NEVILLE', 'NEWTON', 'NICK', 'NICKOLAS', 'NICOLAS', 'NIGEL', 'NOAH',
    'NOBLE', 'NOE', 'NOEL', 'NOLAN', 'NORBERT', 'NORBERTO', 'NORMAN', 'NORRIS',
    'OCTAVIO', 'ODELL', 'ODIS', 'OLIN', 'OLIVER', 'OLIVERIO', 'OLIVIA', 'OMAR',
    'OMER', 'ORLANDO', 'ORVAL', 'OSCAR', 'OSWALDO', 'OTIS', 'OTTO', 'OWEN',
    'PABLO', 'PALMER', 'PARIS', 'PARKER', 'PASCAL', 'PAT', 'PATRICK', 'PAUL',
    'PEDRO', 'PERCY', 'PERRY', 'PETE', 'PETER', 'PHIL', 'PHILIP', 'PHILLIP',
    'PIERRE', 'PORFIRIO', 'PORTER', 'PRESLEY', 'PRIMITIVO', 'PRINCE', 'QUENTIN', 'QUINCY',
    'RAFAEL', 'RAIMUNDO', 'RAMIRO', 'RAMON', 'RANDAL', 'RANDALL', 'RANDOLPH', 'RAPHAEL',
    'RAUL', 'RAY', 'RAYFORD', 'RAYMON', 'RAYMOND', 'RAYMUNDO', 'REED', 'REFUGIO',
    'REGGIE', 'REGINALD', 'REID', 'REINALDO', 'RENALDO', 'RENATO', 'RENE', 'REUBEN',
    'REX', 'REYNALDO', 'RICARDO', 'RICHARD', 'RICK', 'RICKIE', 'RICKY', 'RICO',
    'ROB', 'ROBBIE', 'ROBERT', 'ROBERTO', 'ROBIN', 'ROBT', 'ROCCO', 'ROCHEL',
    'ROCKY', 'ROD', 'RODERICK', 'RODGER', 'RODNEY', 'RODOLFO', 'RODRIGO', 'ROLAND',
    'ROLANDO', 'ROLF', 'ROLLAND', 'ROMAN', 'ROMEO', 'RON', 'RONALD', 'RONNIE',
    'RONNY', 'ROOSEVELT', 'RORY', 'ROSARIO', 'ROSCOE', 'ROSENDO', 'ROSS', 'ROY',
    'ROYAL', 'ROYCE', 'RUBEN', 'RUDOLPH', 'RUDY', 'RUFUS', 'RUPERT', 'RUSSEL',
    'RUSTY', 'RYAN', 'SAL', 'SALVADOR', 'SALVATORE', 'SAM', 'SAMMY', 'SAMUAL',
    'SAMUEL', 'SANDY', 'SANFORD', 'SANTIAGO', 'SANTOS', 'SAUL', 'SCOT', 'SCOTT',
    'SEAN', 'SEBASTIAN', 'SERGIO', 'SETH', 'SEYMOUR', 'SHANE', 'SHANNON', 'SHAUN',
    'SHAWN', 'SHELBY', 'SHELTON', 'SHERMAN', 'SHERWOOD', 'SID', 'SIDNEY', 'SILAS',
    'SIMON', 'SOLOMON', 'SONNY', 'SPENCER', 'STAN', 'STANFORD', 'STANLEY', 'STANTON',
    'STEFAN', 'STEPHAN', 'STEPHEN', 'STERLING', 'STEVE', 'STEVEN', 'STEWART', 'STU',
    'STUART', 'SUNG', 'SYLVESTER', 'SYLVIA', 'TAD', 'TALMADGE', 'TANNER', 'TAYLOR',
    'TED', 'TEODORO', 'TERENCE', 'TERRELL', 'TERRANCE', 'TERRELL', 'TERRY', 'THEO',
    'THEODORE', 'THEODORO', 'THEODORE', 'THOMAS', 'THURMAN', 'TIM', 'TIMMY', 'TIMOTHY',
    'TITUS', 'TOBIAS', 'TOBY', 'TOD', 'TODD', 'TOM', 'TOMAS', 'TOMMY',
    'TONEY', 'TONY', 'TORRY', 'TRACY', 'TRAVIS', 'TRENT', 'TRENTON', 'TREVOR',
    'TROY', 'TRUMAN', 'TUCKER', 'TY', 'TYLER', 'TYRONE', 'ULISES', 'URBANO',
    'VALENTIN', 'VALENTINE', 'VAUGHN', 'VERNON', 'VICENTE', 'VICTOR', 'VINCE', 'VINCENT',
    'VIRGIL', 'VIRGILIO', 'VITO', 'WADE', 'WALKER', 'WALLACE', 'WALLY', 'WALTER',
    'WARNER', 'WARREN', 'WAYNE', 'WELDON', 'WENDELL', 'WERNER', 'WES', 'WESLEY',
    'WESTON', 'WHITNEY', 'WILBER', 'WILBERT', 'WILBUR', 'WILFREDO', 'WILFRED', 'WILL',
    'WILLARD', 'WILLIAM', 'WILLIE', 'WILLIS', 'WILMER', 'WILSON', 'WINTON', 'WM',
    'WOODROW', 'WYATT', 'XAVIER', 'YONG', 'ZACHARIAH', 'ZACHARY', 'ZACK', 'ZANE'
];

$nombres_femeninos = [
    // Nombres femeninos comunes en inglés y español
    'ABBEY', 'ABBY', 'ABIGAIL', 'ADA', 'ADELA', 'ADELAIDE', 'ADELE', 'ADELINE',
    'ADRIANA', 'ADRIENNE', 'AGATHA', 'AGNES', 'AGUSTINA', 'AIDA', 'AILEEN', 'AIMEE',
    'ALANA', 'ALBERTA', 'ALEJANDRA', 'ALEXANDRA', 'ALICIA', 'ALISON', 'ALMA', 'ALONDRA',
    'ALTA', 'ALTHEA', 'ALVA', 'ALVINA', 'AMANDA', 'AMBER', 'AMELIA', 'AMI',
    'AMIE', 'AMINA', 'AMITA', 'AMMIE', 'AMY', 'ANA', 'ANABEL', 'ANABELLA',
    'ANALISA', 'ANAMARIA', 'ANASTACIA', 'ANASTASIA', 'ANDREA', 'ANGELA', 'ANGELENA', 'ANGELES',
    'ANGELICA', 'ANGELINA', 'ANGELIQUE', 'ANGIE', 'ANGELA', 'ANITA', 'ANN', 'ANNA',
    'ANNE', 'ANNETTE', 'ANNIE', 'ANTONETTA', 'ANTONIA', 'ANTONIETTA', 'ANTONY', 'APRIL',
    'ARACELI', 'ARACELY', 'ARCELIA', 'ARETHA', 'ARGENTINA', 'ARIANA', 'ARIANE', 'ARIANNA',
    'ARIEL', 'ARLEEN', 'ARLENA', 'ARLENE', 'ARLINE', 'ARLYNE', 'ARMANDA', 'ARMIDA',
    'ARTEMISIA', 'ARTHUR', 'ASHLEE', 'ASHLEY', 'ASIA', 'AUDREY', 'AVA', 'AVELINA',
    'AVERY', 'AVIS', 'BARB', 'BARBARA', 'BEATRICE', 'BECKY', 'BELINDA', 'BELLA',
    'BENITA', 'BERENICE', 'BERNADETTE', 'BERNICE', 'BERNIE', 'BERTA', 'BERTHA', 'BESS',
    'BESSIE', 'BETH', 'BETHANY', 'BETHENA', 'BETSY', 'BETTE', 'BETTY', 'BEULAH',
    'BEV', 'BEVERLY', 'BIANCA', 'BILLIE', 'BLAIR', 'BLAKE', 'BLANCA', 'BLANCH',
    'BLANCHE', 'BONITA', 'BONNIE', 'BRANDA', 'BRANDEE', 'BRANDI', 'BRANDIE', 'BRANDY',
    'BREANNA', 'BREE', 'BRENDA', 'BRENDY', 'BRENNA', 'BRIANNA', 'BRIDGET', 'BRIDGETT',
    'BRIGITTE', 'BRITT', 'BRITTANI', 'BRITTANY', 'BRITTNEY', 'BRITNY', 'BROOKE', 'BROOKLYN',
    'BRUNA', 'BRYANNA', 'BRYN', 'BRYNN', 'BUCK', 'BUDDY', 'BUFFY', 'BUNNY',
    'CADY', 'CAITLIN', 'CAITLYN', 'CALANDRA', 'CALEEN', 'CALISTA', 'CALLIE', 'CALVIN',
    'CAMERON', 'CAMELLIA', 'CAMILA', 'CAMILLA', 'CANDACE', 'CANDIDA', 'CANDIE', 'CANDIS',
    'CANDY', 'CAPRICE', 'CARA', 'CAREN', 'CAREY', 'CARI', 'CARIN', 'CARINA',
    'CARISA', 'CARL', 'CARLA', 'CARLEE', 'CARLEEN', 'CARLENE', 'CARLETTA', 'CARLEY',
    'CARLI', 'CARLIE', 'CARLOTA', 'CARLOTTA', 'CARLTON', 'CARLY', 'CARLYN', 'CARMEL',
    'CARMELA', 'CARMELIA', 'CARMELINA', 'CARMELITA', 'CARMELLA', 'CARMEN', 'CARMINA', 'CAROL',
    'CAROLA', 'CAROLANN', 'CAROLE', 'CAROLEE', 'CAROLINA', 'CAROLINE', 'CAROLYN', 'CARON',
    'CAROYLN', 'CARRIE', 'CARRY', 'CARY', 'CARYL', 'CARYN', 'CASEY', 'CASSANDRA',
    'CASSIE', 'CATALINA', 'CATE', 'CATHERINE', 'CATHEY', 'CATHI', 'CATHIE', 'CATHLEEN',
    'CATHRINE', 'CATHRYN', 'CATI', 'CATINA', 'CATRINA', 'CAYLA', 'CECELIA', 'CECIL',
    'CECILE', 'CECILIA', 'CELESTE', 'CELESTINA', 'CELINE', 'CHARA', 'CHARIS', 'CHARITY',
    'CHARLA', 'CHARLEEN', 'CHARLENE', 'CHARLES', 'CHARLESETTA', 'CHARLETTE', 'CHARLEY', 'CHARLIE',
    'CHARLINE', 'CHARLOTTE', 'CHARLYN', 'CHASITY', 'CHASTITY', 'CHER', 'CHERE', 'CHEREE',
    'CHERI', 'CHERIE', 'CHERILYN', 'CHERISE', 'CHERISH', 'CHERLYN', 'CHERRI', 'CHERRIE',
    'CHERRY', 'CHERY', 'CHERYL', 'CHEYENNE', 'CHI', 'CHLOE', 'CHRIS', 'CHRISTA',
    'CHRISTEEN', 'CHRISTEL', 'CHRISTEN', 'CHRISTI', 'CHRISTIAN', 'CHRISTIANA', 'CHRISTIE', 'CHRISTIN',
    'CHRISTINA', 'CHRISTINE', 'CHRISTY', 'CHRYSTAL', 'CICELY', 'CIELA', 'CINDA', 'CINDERELLA',
    'CINDI', 'CINDIE', 'CINDY', 'CINTHIA', 'CIRA', 'CLAIRE', 'CLARA', 'CLARE',
    'CLARICE', 'CLARISA', 'CLARISSA', 'CLAUDE', 'CLAUDETTE', 'CLAUDIA', 'CLAUDIE', 'CLAUDINE',
    'CLEMENCIA', 'CLEO', 'CLEOPATRA', 'CLORINDA', 'CLOTILDE', 'CLOVER', 'COLLEEN', 'COLLETTE',
    'COLLENE', 'CONCEPCION', 'CONCHITA', 'CONNIE', 'CONNY', 'CONSUELA', 'CONSUELO', 'COOK',
    'CORA', 'CORAL', 'CORALIE', 'CORAZON', 'CORDELIA', 'CORINNA', 'CORINNE', 'CORLISS',
    'CORNELIA', 'CORY', 'COURTNEY', 'CRISTAL', 'CRISTI', 'CRISTIE', 'CRISTINA', 'CRISTINE',
    'CRISTY', 'CRUZ', 'CRYSTAL', 'CYNTHIA', 'CYRSTAL', 'DAISY', 'DAKOTA', 'DALE',
    'DALIA', 'DALLAS', 'DALTON', 'DAMARIS', 'DANA', 'DANAE', 'DANE', 'DANELLE',
    'DANETTE', 'DANI', 'DANIA', 'DANICA', 'DANIEL', 'DANIELA', 'DANIELE', 'DANIELLA',
    'DANIKA', 'DANITA', 'DANNA', 'DANNAH', 'DAPHNE', 'DARA', 'DARBY', 'DARCEL',
    'DARCEY', 'DARCY', 'DARD', 'DARELL', 'DARIA', 'DARLA', 'DARLEEN', 'DARLENE',
    'DARNELL', 'DARREL', 'DARRELL', 'DARRYL', 'DARYL', 'DAVE', 'DAVID', 'DAVINA',
    'DAWN', 'DAYLE', 'DAYNA', 'DEANA', 'DEANDRA', 'DEANN', 'DEANNA', 'DEANNE',
    'DEB', 'DEBBIE', 'DEBBRA', 'DEBBI', 'DEBBIE', 'DEBBRA', 'DEBERA', 'DEBI',
    'DEBORAH', 'DEBRA', 'DEBROAH', 'DEE', 'DEEANN', 'DEEDEE', 'DEENA', 'DEIDRA',
    'DEIDRE', 'DEIRDRE', 'DELIA', 'DELILA', 'DELILAH', 'DELL', 'DELLA', 'DELMA',
    'DELOIS', 'DELOISE', 'DELORAS', 'DELTA', 'DEMETRA', 'DEMI', 'DEON', 'DEONA',
    'DESIREE', 'DESPINA', 'DESSIE', 'DIAN', 'DIANA', 'DIANE', 'DIANN', 'DIANNA',
    'DIANNE', 'DICK', 'DINA', 'DINAH', 'DINO', 'DIXIE', 'DJ', 'DOLORES',
    'DOMENICA', 'DOMINGA', 'DOMINICA', 'DOMINIQUE', 'DONA', 'DONALD', 'DONETTA', 'DONITA',
    'DONNA', 'DONNIE', 'DONYA', 'DORA', 'DORATHY', 'DORCAS', 'DOREATHA', 'DOREEN',
    'DORINDA', 'DORINE', 'DORIS', 'DOROTHA', 'DOROTHEA', 'DOROTHY', 'DORTHA', 'DORTHEA',
    'DOT', 'DOTTIE', 'DOTTY', 'DRUCILLA', 'DRUSILLA', 'DUENAS', 'DUKE', 'DULCE',
    'DULCIE', 'DUSTY', 'DWANA', 'DYAN', 'DYANNE', 'EARLEAN', 'EARLEEN', 'EARLENE',
    'EARLIE', 'EARLINE', 'EARNESTINE', 'EBONI', 'EBONY', 'EDDA', 'EDEN', 'EDIE',
    'EDNA', 'EDWARD', 'EDWINA', 'EFFIE', 'EILEEN', 'EILENE', 'ELA', 'ELAINA',
    'ELAINE', 'ELANA', 'ELAYNE', 'ELBA', 'ELDA', 'ELEANORA', 'ELEANORE', 'ELEASE',
    'ELFRIEDA', 'ELI', 'ELIA', 'ELIANA', 'ELICIA', 'ELIDA', 'ELINOR', 'ELINORE',
    'ELISA', 'ELISABETH', 'ELISE', 'ELISHA', 'ELISSA', 'ELIZA', 'ELIZABETH', 'ELIZBETH',
    'ELKE', 'ELLA', 'ELLAMA', 'ELLEN', 'ELLI', 'ELLIE', 'ELLIOT', 'ELLYN',
    'ELMA', 'ELMER', 'ELMIRA', 'ELOISA', 'ELOISE', 'ELSA', 'ELSIE', 'ELVA',
    'ELVERA', 'ELVIA', 'ELVIE', 'ELVIN', 'ELVIRA', 'ELWANDA', 'ELWOOD', 'ELYSE',
    'EMELDA', 'EMERITA', 'EMILIA', 'EMILIE', 'EMILY', 'EMMA', 'EMMALINE', 'EMMIE',
    'EMMY', 'ERICA', 'ERIKA', 'ERIN', 'ERLENE', 'ERMA', 'ERMINIA', 'ERNA',
    'ERNESTINA', 'ERNESTINE', 'ESMERALDA', 'ESPERANZA', 'ESSIE', 'ESTA', 'ESTELA', 'ESTELLA',
    'ESTELLE', 'ESTER', 'ESTHER', 'ESTRELLA', 'ETHEL', 'ETTA', 'ETTIE', 'EUFEMIA',
    'EUGENA', 'EUGENE', 'EUGENIA', 'EULA', 'EULALIA', 'EUNICE', 'EURA', 'EVA',
    'EVALYN', 'EVANGELINA', 'EVE', 'EVELIA', 'EVELIN', 'EVELINA', 'EVELINE', 'EVELYN',
    'EVEN', 'EVERETTE', 'EVIA', 'EVIE', 'EVITA', 'EVON', 'EVONNE', 'EWELL',
    'EXIE', 'EZEKIEL', 'EZELLA', 'EZMA', 'EZRA', 'FABIOLA', 'FABIOLA', 'FABIOLA',
    'FABIOLA', 'FABIOLA', 'FABIOLA', 'FABIOLA', 'FABIOLA', 'FABIOLA', 'FABIOLA', 'FABIOLA'
];

// ======================================================================
// PASO 1: OBTENER PERSONAS NATURALES CON NOMBRE COMPLETO
// ======================================================================

echo "[INFO] Obteniendo personas naturales con nombre completo...\n";

$stmt = $m->prepare("
    SELECT 
        id_cliente,
        nombre,
        apellido,
        id_sexo,
        id_tratamiento
    FROM clientes
    WHERE id_tipo_persona = 1
      AND TRIM(nombre) != ''
      AND (apellido IS NULL OR TRIM(apellido) = '')
    ORDER BY nombre ASC
    LIMIT 10000
");
$stmt->execute();
$result = $stmt->get_result();

$separaciones = [];
$total_procesados = 0;

while ($row = $result->fetch_assoc()) {
    $total_procesados++;
    
    // Limpiar nombre completo
    $nombre_limpio = limpiarNombreCompleto($row['nombre']);
    
    if (empty($nombre_limpio)) {
        continue; // Saltar si queda vacío después de limpieza
    }
    
    // Separar en nombre y apellido
    $separado = separarNombreCompleto($nombre_limpio);
    
    if (!$separado['completo']) {
        continue; // Saltar si no se puede separar (solo un nombre)
    }
    
    // Detectar sexo basado en primer nombre
    $primer_nombre = strtoupper(trim($separado['nombre']));
    $sexo = NULL;
    $tratamiento = NULL;
    
    if (in_array($primer_nombre, $nombres_femeninos)) {
        $sexo = 2; // Femenino
        $tratamiento = 2; // Mrs.
    } elseif (in_array($primer_nombre, $nombres_masculinos)) {
        $sexo = 1; // Masculino
        $tratamiento = 1; // Mr.
    }
    // Si no está en ninguna lista, sexo = NULL (requiere verificación manual)
    
    $separaciones[] = [
        'id_cliente' => $row['id_cliente'],
        'nombre_original' => $row['nombre'],
        'nombre_limpio' => $nombre_limpio,
        'nombre_propuesto' => $separado['nombre'],
        'apellido_propuesto' => $separado['apellido'],
        'sexo_propuesto' => $sexo,
        'tratamiento_propuesto' => $tratamiento,
        'sexo_actual' => $row['id_sexo'],
        'tratamiento_actual' => $row['id_tratamiento'],
        'requiere_verificacion' => ($sexo === NULL)
    ];
}
$stmt->close();

$total_separaciones = count($separaciones);
echo "[OK] $total_procesados nombres procesados\n";
echo "     $total_separaciones nombres separados exitosamente\n\n";

// ======================================================================
// PASO 2: GENERAR REPORTE CSV PARA REVISIÓN MANUAL
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  GENERANDO REPORTE CSV PARA REVISIÓN MANUAL                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$csv_file = '/var/www/greentrack/scripts/separacion_nombres_limpieza.csv';
$fp = fopen($csv_file, 'w');

// Header
fputcsv($fp, [
    'ID_CLIENTE',
    'NOMBRE_ORIGINAL',
    'NOMBRE_LIMPIO',
    'NOMBRE_PROPUESTO',
    'APELLIDO_PROPUESTO',
    'SEXO_PROPUESTO',
    'TRATAMIENTO_PROPUESTO',
    'SEXO_ACTUAL',
    'TRATAMIENTO_ACTUAL',
    'REQUIERE_VERIFICACION',
    'VERIFICADO_MANUAL'
], ';');

// Datos
foreach ($separaciones as $sep) {
    fputcsv($fp, [
        $sep['id_cliente'],
        $sep['nombre_original'],
        $sep['nombre_limpio'],
        $sep['nombre_propuesto'],
        $sep['apellido_propuesto'],
        $sep['sexo_propuesto'] ?? 'NULL',
        $sep['tratamiento_propuesto'] ?? 'NULL',
        $sep['sexo_actual'] ?? 'NULL',
        $sep['tratamiento_actual'] ?? 'NULL',
        $sep['requiere_verificacion'] ? 'SI' : 'NO',
        'NO' // Por defecto no verificado
    ], ';');
}
fclose($fp);

echo "[OK] Reporte CSV generado: $csv_file\n\n";

// ======================================================================
// PASO 3: ESTADÍSTICAS Y EJEMPLOS
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS Y EJEMPLOS                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Contar por sexo detectado
$sexo_masc = count(array_filter($separaciones, fn($s) => $s['sexo_propuesto'] == 1));
$sexo_fem = count(array_filter($separaciones, fn($s) => $s['sexo_propuesto'] == 2));
$sexo_indet = count(array_filter($separaciones, fn($s) => $s['sexo_propuesto'] === NULL));

echo "Estadísticas de detección de sexo:\n";
echo "  Masculino detectado: $sexo_masc\n";
echo "  Femenino detectado: $sexo_fem\n";
echo "  Indeterminado (requiere verificación): $sexo_indet\n\n";

// Mostrar ejemplos
echo "Ejemplos de separación (primeros 5):\n";
$count = 0;
foreach ($separaciones as $sep) {
    if ($count >= 5) break;
    
    echo "  ID {$sep['id_cliente']}:\n";
    echo "    Original: '{$sep['nombre_original']}'\n";
    echo "    Limpio: '{$sep['nombre_limpio']}'\n";
    echo "    Nombre: '{$sep['nombre_propuesto']}' | Apellido: '{$sep['apellido_propuesto']}'\n";
    echo "    Sexo: " . ($sep['sexo_propuesto'] ?? 'INDETERMINADO') . " | Tratamiento: " . ($sep['tratamiento_propuesto'] ?? 'INDETERMINADO') . "\n\n";
    
    $count++;
}

// ======================================================================
// PASO 4: INSTRUCCIONES PARA EL USUARIO
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  INSTRUCCIONES PARA REVISIÓN MANUAL                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "📋 PASOS RECOMENDADOS:\n\n";

echo "1. ABRE EL CSV EN EXCEL:\n";
echo "   Archivo: $csv_file\n";
echo "   Importa con delimitador ';'\n\n";

echo "2. REVISIÓN DE COLUMNAS:\n";
echo "   • NOMBRE_ORIGINAL: Nombre original en la BD\n";
echo "   • NOMBRE_LIMPIO: Nombre después de eliminar caracteres especiales\n";
echo "   • NOMBRE_PROPUESTO: Primer nombre detectado\n";
echo "   • APELLIDO_PROPUESTO: Apellido(s) detectado(s)\n";
echo "   • SEXO_PROPUESTO: 1=Masculino, 2=Femenino, NULL=Indeterminado\n";
echo "   • TRATAMIENTO_PROPUESTO: 1=Mr., 2=Mrs., NULL=Indeterminado\n";
echo "   • REQUIERE_VERIFICACION: SI si sexo indeterminado, NO si detectado\n\n";

echo "3. VERIFICACIÓN MANUAL:\n";
echo "   • Filtra por REQUIERE_VERIFICACION = 'SI'\n";
echo "   • Para cada registro indeterminado:\n";
echo "     - Determina el sexo correcto basado en el nombre\n";
echo "     - Asigna SEXO_PROPUESTO (1 o 2)\n";
echo "     - Asigna TRATAMIENTO_PROPUESTO (1 o 2)\n";
echo "   • Verifica que la separación nombre/apellido sea correcta\n";
echo "   • Corrige cualquier error en NOMBRE_PROPUESTO o APELLIDO_PROPUESTO\n\n";

echo "4. MARCAR COMO VERIFICADO:\n";
echo "   • Cambia VERIFICADO_MANUAL = 'SI' para los registros revisados\n";
echo "   • Guarda el archivo como: separacion_nombres_limpieza_VERIFICADO.csv\n\n";

echo "5. PRÓXIMO PASO:\n";
echo "   • Una vez verificado, ejecutaremos el script de actualización\n";
echo "   • Ese script aplicará los cambios a la tabla CLIENTES\n\n";

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN                                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Limpieza completa: Elimina números, puntuación y caracteres especiales\n";
echo "✅ Separación automática: Nombre + Apellido\n";
echo "✅ Detección de sexo: Basada en listas extensas de nombres\n";
echo "✅ Tratamiento automático: Mr./Mrs. según sexo detectado\n";
echo "✅ CSV para revisión: Total control manual antes de aplicar cambios\n";
echo "✅ Seguridad: Sin modificaciones a la BD hasta tu confirmación\n\n";

$m->close();

echo "[FIN] Separación de nombres y apellidos con limpieza completa\n\n";

?>