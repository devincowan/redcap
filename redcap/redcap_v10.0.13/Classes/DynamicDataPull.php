<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\FhirData;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirLogsMapper;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;

/**
 * DynamicDataPull
 * This class is used for setup and execution of the real-time web service for extracting
 * data from external systems and importing into REDCap.
 */
class DynamicDataPull
{
	// Encryption salt for DDP source values
	const DDP_ENCRYPTION_KEY = "ds9p2PGh#hK4aV@GVH-YbPrtpWp*7SpeBW+RTujYHj%q35aOrQO/aCSVIFMKifl!S6Ql~JV";

	// Cron's record fetch limit per batch
	const FETCH_LIMIT_PER_BATCH = 20;

	// Cron's record limit per query to the log_event when checking the last time a record was modified
	const RECORD_LIMIT_PER_LOG_QUERY = 20;

	// Set min/max values for range for Day Offset and Default Day Offset
	const DAY_OFFSET_MIN = 0.01;
	const DAY_OFFSET_MAX = 365;

	// Current project_id for this object
	public $project_id = null;
	
	// Store the DDP type (FHIR or CUSTOM)
	public $realtime_webservice_type = null;

	// Variable to store this project's field mappings as an array
	public $field_mappings = null;
	
	// Store FHIR service endpoint information (full URL, params) in array
	private $fhirEndPointsParams = array();
	
	public $nonTemporalMultipleValueFields = array();
	
	// Map the DDP metadata category to FHIR service endpoint category
	private $fhirMetadataCategoryMapping = array(
		// DDP metadata category => FHIR endpoint category
		'Demographics' => 'Patient',
		'Vital Signs' => 'Observation',
		'Laboratory' => 'Observation',
		'Condition' => 'Condition',
		'Medications' => 'MedicationOrder',
		'Allergy Intolerance' => 'AllergyIntolerance'
	);
	
	// LOINC Lab files to use as curated 
	public static $fhirMetadataLabListFiles = array('loinc_labs_utsw_lrr.csv', 'loinc_labs_vanderbilt_crosswalk.csv', 
													'loinc_labs_vanderbilt_rd.csv', 'loinc_labs_additions.csv');
	public static $fhirMetadataLabList = array();
	
	// Field choice mapping for multiple choice demography fields	
	public static $demography_mc_mapping = array(	
		'gender'=>"F, Female | M, Male | UNK, Unknown", 
		'ethnicity'=>"2135-2, Hispanic or Latino | 2186-5, Not Hispanic or Latino | 2137-8, Spaniard | 2148-5, Mexican | 2155-0, Central American | 2165-9, South American | 2178-2, Latin American | 2180-8, Puerto Rican | 2182-4, Cuban | 2184-0, Dominican | 2138-6, Andalusian | 2139-4, Asturian | 2140-2, Castillian | 2141-0, Catalonian | 2142-8, Belearic Islander | 2143-6, Gallego | 2144-4, Valencian | 2145-1, Canarian | 2146-9, Spanish Basque | 2149-3, Mexican American | 2150-1, Mexicano | 2151-9, Chicano | 2152-7, La Raza | 2153-5, Mexican American Indian | 2156-8, Costa Rican | 2157-6, Guatemalan | 2158-4, Honduran | 2159-2, Nicaraguan | 2160-0, Panamanian | 2161-8, Salvadoran | 2162-6, Central American Indian | 2163-4, Canal Zone | 2166-7, Argentinean | 2167-5, Bolivian | 2168-3, Chilean | 2169-1, Colombian | 2170-9, Ecuadorian | 2171-7, Paraguayan | 2172-5, Peruvian | 2173-3, Uruguayan | 2174-1, Venezuelan | 2175-8, South American Indian | 2176-6, Criollo | UNK, Unknown", 
		'race'=>"1002-5, American Indian/Alaska Native | 2028-9, Asian | 2076-8, Native Hawaiian or Other Pacific Islander | 2054-5, Black or African American | 2106-3, White | 1004-1, American Indian | 1735-0, Alaska Native | 1006-6, Abenaki | 1008-2, Algonquian | 1010-8, Apache | 1021-5, Arapaho | 1026-4, Arikara | 1028-0, Assiniboine | 1030-6, Assiniboine Sioux | 1033-0, Bannock | 1035-5, Blackfeet | 1037-1, Brotherton | 1039-7, Burt Lake Band | 1041-3, Caddo | 1044-7, Cahuilla | 1053-8, California Tribes | 1068-6, Canadian and Latin American Indian | 1076-9, Catawba | 1078-5, Cayuse | 1080-1, Chehalis | 1082-7, Chemakuan | 1086-8, Chemehuevi | 1088-4, Cherokee | 1100-7, Cherokee Shawnee | 1102-3, Cheyenne | 1106-4, Cheyenne-Arapaho | 1108-0, Chickahominy | 1112-2, Chickasaw | 1114-8, Chinook | 1123-9, Chippewa | 1150-2, Chippewa Cree | 1153-6, Chitimacha | 1155-1, Choctaw | 1162-7, Chumash | 1165-0, Clear Lake | 1167-6, Coeur D'Alene | 1169-2, Coharie | 1171-8, Colorado River | 1173-4, Colville | 1175-9, Comanche | 1178-3, Coos, Lower Umpqua, Siuslaw | 1180-9, Coos | 1182-5, Coquilles | 1184-1, Costanoan | 1186-6, Coushatta | 1189-0, Cowlitz | 1191-6, Cree | 1193-2, Creek | 1207-0, Croatan | 1209-6, Crow | 1211-2, Cupeno | 1214-6, Delaware | 1222-9, Diegueno | 1233-6, Eastern Tribes | 1250-0, Esselen | 1252-6, Fort Belknap | 1254-2, Fort Berthold | 1256-7, Fort Mcdowell | 1258-3, Fort Hall | 1260-9, Gabrieleno | 1262-5, Grand Ronde | 1264-1, Gros Ventres | 1267-4, Haliwa | 1269-0, Hidatsa | 1271-6, Hoopa | 1275-7, Hoopa Extension | 1277-3, Houma | 1279-9, Inaja-Cosmit | 1281-5, Iowa | 1285-6, Iroquois | 1297-1, Juaneno | 1299-7, Kalispel | 1301-1, Karuk | 1303-7, Kaw | 1305-2, Kickapoo | 1309-4, Kiowa | 1312-8, Klallam | 1317-7, Klamath | 1319-3, Konkow | 1321-9, Kootenai | 1323-5, Lassik | 1325-0, Long Island | 1331-8, Luiseno | 1340-9, Lumbee | 1342-5, Lummi | 1344-1, Maidu | 1348-2, Makah | 1350-8, Maliseet | 1352-4, Mandan | 1354-0, Mattaponi | 1356-5, Menominee | 1358-1, Miami | 1363-1, Miccosukee | 1365-6, Micmac | 1368-0, Mission Indians | 1370-6, Miwok | 1372-2, Modoc | 1374-8, Mohegan | 1376-3, Mono | 1378-9, Nanticoke | 1380-5, Narragansett | 1382-1, Navajo | 1387-0, Nez Perce | 1389-6, Nomalaki | 1391-2, Northwest Tribes | 1403-5, Omaha | 1405-0, Oregon Athabaskan | 1407-6, Osage | 1409-2, Otoe-Missouria | 1411-8, Ottawa | 1416-7, Paiute | 1439-9, Pamunkey | 1441-5, Passamaquoddy | 1445-6, Pawnee | 1448-0, Penobscot | 1450-6, Peoria | 1453-0, Pequot | 1456-3, Pima | 1460-5, Piscataway | 1462-1, Pit River | 1464-7, Pomo | 1474-6, Ponca | 1478-7, Potawatomi | 1487-8, Powhatan | 1489-4, Pueblo | 1518-0, Puget Sound Salish | 1541-2, Quapaw | 1543-8, Quinault | 1545-3, Rappahannock | 1547-9, Reno-Sparks | 1549-5, Round Valley | 1551-1, Sac and Fox | 1556-0, Salinan | 1558-6, Salish | 1560-2, Salish and Kootenai | 1562-8, Schaghticoke | 1564-4, Scott Valley | 1566-9, Seminole | 1573-5, Serrano | 1576-8, Shasta | 1578-4, Shawnee | 1582-6, Shinnecock | 1584-2, Shoalwater Bay | 1586-7, Shoshone | 1602-2, Shoshone Paiute | 1607-1, Siletz | 1609-7, Sioux | 1643-6, Siuslaw | 1645-1, Spokane | 1647-7, Stewart | 1649-3, Stockbridge | 1651-9, Susanville | 1653-5, Tohono O'Odham | 1659-2, Tolowa | 1661-8, Tonkawa | 1663-4, Tygh | 1665-9, Umatilla | 1667-5, Umpqua | 1670-9, Ute | 1675-8, Wailaki | 1677-4, Walla-Walla | 1679-0, Wampanoag | 1683-2, Warm Springs | 1685-7, Wascopum | 1687-3, Washoe | 1692-3, Wichita | 1694-9, Wind River | 1696-4, Winnebago | 1700-4, Winnemucca | 1702-0, Wintun | 1704-6, Wiyot | 1707-9, Yakama | 1709-5, Yakama Cowlitz | 1711-1, Yaqui | 1715-2, Yavapai Apache | 1717-8, Yokuts | 1722-8, Yuchi | 1724-4, Yuman | 1732-7, Yurok | 1011-6, Chiricahua | 1012-4, Fort Sill Apache | 1013-2, Jicarilla Apache | 1014-0, Lipan Apache | 1015-7, Mescalero Apache | 1016-5, Oklahoma Apache | 1017-3, Payson Apache | 1018-1, San Carlos Apache | 1019-9, White Mountain Apache | 1022-3, Northern Arapaho | 1023-1, Southern Arapaho | 1024-9, Wind River Arapaho | 1031-4, Fort Peck Assiniboine Sioux | 1042-1, Oklahoma Cado | 1045-4, Agua Caliente Cahuilla | 1046-2, Augustine | 1047-0, Cabazon | 1048-8, Los Coyotes | 1049-6, Morongo | 1050-4, Santa Rosa Cahuilla | 1051-2, Torres-Martinez | 1054-6, Cahto | 1055-3, Chimariko | 1056-1, Coast Miwok | 1057-9, Digger | 1058-7, Kawaiisu | 1059-5, Kern River | 1060-3, Mattole | 1061-1, Red Wood | 1062-9, Santa Rosa | 1063-7, Takelma | 1064-5, Wappo | 1065-2, Yana | 1066-0, Yuki | 1069-4, Canadian Indian | 1070-2, Central American Indian | 1071-0, French American Indian | 1072-8, Mexican American Indian | 1073-6, South American Indian | 1074-4, Spanish American Indian | 1083-5, Hoh | 1084-3, Quileute | 1089-2, Cherokee Alabama | 1090-0, Cherokees of Northeast Alabama | 1091-8, Cherokees of Southeast Alabama | 1092-6, Eastern Cherokee | 1093-4, Echota Cherokee | 1094-2, Etowah Cherokee | 1095-9, Northern Cherokee | 1096-7, Tuscola | 1097-5, United Keetowah Band of Cherokee | 1098-3, Western Cherokee | 1103-1, Northern Cheyenne | 1104-9, Southern Cheyenne | 1109-8, Eastern Chickahominy | 1110-6, Western Chickahominy | 1115-5, Clatsop | 1116-3, Columbia River Chinook | 1117-1, Kathlamet | 1118-9, Upper Chinook | 1119-7, Wakiakum Chinook | 1120-5, Willapa Chinook | 1121-3, Wishram | 1124-7, Bad River | 1125-4, Bay Mills Chippewa | 1126-2, Bois Forte | 1127-0, Burt Lake Chippewa | 1128-8, Fond du Lac | 1129-6, Grand Portage | 1130-4, Grand Traverse Band of Ottawa/Chippewa | 1131-2, Keweenaw | 1132-0, Lac Courte Oreilles | 1133-8, Lac du Flambeau | 1134-6, Lac Vieux Desert Chippewa | 1135-3, Lake Superior | 1136-1, Leech Lake | 1137-9, Little Shell Chippewa | 1138-7, Mille Lacs | 1139-5, Minnesota Chippewa | 1140-3, Ontonagon | 1141-1, Red Cliff Chippewa | 1142-9, Red Lake Chippewa | 1143-7, Saginaw Chippewa | 1144-5, St. Croix Chippewa | 1145-2, Sault Ste. Marie Chippewa | 1146-0, Sokoagon Chippewa | 1147-8, Turtle Mountain | 1148-6, White Earth | 1151-0, Rocky Boy's Chippewa Cree | 1156-9, Clifton Choctaw | 1157-7, Jena Choctaw | 1158-5, Mississippi Choctaw | 1159-3, Mowa Band of Choctaw | 1160-1, Oklahoma Choctaw | 1163-5, Santa Ynez | 1176-7, Oklahoma Comanche | 1187-4, Alabama Coushatta | 1194-0, Alabama Creek | 1195-7, Alabama Quassarte | 1196-5, Eastern Creek | 1197-3, Eastern Muscogee | 1198-1, Kialegee | 1199-9, Lower Muscogee | 1200-5, Machis Lower Creek Indian | 1201-3, Poarch Band | 1202-1, Principal Creek Indian Nation | 1203-9, Star Clan of Muscogee Creeks | 1204-7, Thlopthlocco | 1205-4, Tuckabachee | 1212-0, Agua Caliente | 1215-3, Eastern Delaware | 1216-1, Lenni-Lenape | 1217-9, Munsee | 1218-7, Oklahoma Delaware | 1219-5, Rampough Mountain | 1220-3, Sand Hill | 1223-7, Campo | 1224-5, Capitan Grande | 1225-2, Cuyapaipe | 1226-0, La Posta | 1227-8, Manzanita | 1228-6, Mesa Grande | 1229-4, San Pasqual | 1230-2, Santa Ysabel | 1231-0, Sycuan | 1234-4, Attacapa | 1235-1, Biloxi | 1236-9, Georgetown (Eastern Tribes) | 1237-7, Moor | 1238-5, Nansemond | 1239-3, Natchez | 1240-1, Nausu Waiwash | 1241-9, Nipmuc | 1242-7, Paugussett | 1243-5, Pocomoke Acohonock | 1244-3, Southeastern Indians | 1245-0, Susquehanock | 1246-8, Tunica Biloxi | 1247-6, Waccamaw-Siousan | 1248-4, Wicomico | 1265-8, Atsina | 1272-4, Trinity | 1273-2, Whilkut | 1282-3, Iowa of Kansas-Nebraska | 1283-1, Iowa of Oklahoma | 1286-4, Cayuga | 1287-2, Mohawk | 1288-0, Oneida | 1289-8, Onondaga | 1290-6, Seneca | 1291-4, Seneca Nation | 1292-2, Seneca-Cayuga | 1293-0, Tonawanda Seneca | 1294-8, Tuscarora | 1295-5, Wyandotte | 1306-0, Oklahoma Kickapoo | 1307-8, Texas Kickapoo | 1310-2, Oklahoma Kiowa | 1313-6, Jamestown | 1314-4, Lower Elwha | 1315-1, Port Gamble Klallam | 1326-8, Matinecock | 1327-6, Montauk | 1328-4, Poospatuck | 1329-2, Setauket | 1332-6, La Jolla | 1333-4, Pala | 1334-2, Pauma | 1335-9, Pechanga | 1336-7, Soboba | 1337-5, Twenty-Nine Palms | 1338-3, Temecula | 1345-8, Mountain Maidu | 1346-6, Nishinam | 1359-9, Illinois Miami | 1360-7, Indiana Miami | 1361-5, Oklahoma Miami | 1366-4, Aroostook | 1383-9, Alamo Navajo | 1384-7, Canoncito Navajo | 1385-4, Ramah Navajo | 1392-0, Alsea | 1393-8, Celilo | 1394-6, Columbia | 1395-3, Kalapuya | 1396-1, Molala | 1397-9, Talakamish | 1398-7, Tenino | 1399-5, Tillamook | 1400-1, Wenatchee | 1401-9, Yahooskin | 1412-6, Burt Lake Ottawa | 1413-4, Michigan Ottawa | 1414-2, Oklahoma Ottawa | 1417-5, Bishop | 1418-3, Bridgeport | 1419-1, Burns Paiute | 1420-9, Cedarville | 1421-7, Fort Bidwell | 1422-5, Fort Independence | 1423-3, Kaibab | 1424-1, Las Vegas | 1425-8, Lone Pine | 1426-6, Lovelock | 1427-4, Malheur Paiute | 1428-2, Moapa | 1429-0, Northern Paiute | 1430-8, Owens Valley | 1431-6, Pyramid Lake | 1432-4, San Juan Southern Paiute | 1433-2, Southern Paiute | 1434-0, Summit Lake | 1435-7, Utu Utu Gwaitu Paiute | 1436-5, Walker River | 1437-3, Yerington Paiute | 1442-3, Indian Township | 1443-1, Pleasant Point Passamaquoddy | 1446-4, Oklahoma Pawnee | 1451-4, Oklahoma Peoria | 1454-8, Marshantucket Pequot | 1457-1, Gila River Pima-Maricopa | 1458-9, Salt River Pima-Maricopa | 1465-4, Central Pomo | 1466-2, Dry Creek | 1467-0, Eastern Pomo | 1468-8, Kashia | 1469-6, Northern Pomo | 1470-4, Scotts Valley | 1471-2, Stonyford | 1472-0, Sulphur Bank | 1475-3, Nebraska Ponca | 1476-1, Oklahoma Ponca | 1479-5, Citizen Band Potawatomi | 1480-3, Forest County | 1481-1, Hannahville | 1482-9, Huron Potawatomi | 1483-7, Pokagon Potawatomi | 1484-5, Prairie Band | 1485-2, Wisconsin Potawatomi | 1490-2, Acoma | 1491-0, Arizona Tewa | 1492-8, Cochiti | 1493-6, Hopi | 1494-4, Isleta | 1495-1, Jemez | 1496-9, Keres | 1497-7, Laguna | 1498-5, Nambe | 1499-3, Picuris | 1500-8, Piro | 1501-6, Pojoaque | 1502-4, San Felipe | 1503-2, San Ildefonso | 1504-0, San Juan Pueblo | 1505-7, San Juan De | 1506-5, San Juan | 1507-3, Sandia | 1508-1, Santa Ana | 1509-9, Santa Clara | 1510-7, Santo Domingo | 1511-5, Taos | 1512-3, Tesuque | 1513-1, Tewa | 1514-9, Tigua | 1515-6, Zia | 1516-4, Zuni | 1519-8, Duwamish | 1520-6, Kikiallus | 1521-4, Lower Skagit | 1522-2, Muckleshoot | 1523-0, Nisqually | 1524-8, Nooksack | 1525-5, Port Madison | 1526-3, Puyallup | 1527-1, Samish | 1528-9, Sauk-Suiattle | 1529-7, Skokomish | 1530-5, Skykomish | 1531-3, Snohomish | 1532-1, Snoqualmie | 1533-9, Squaxin Island | 1534-7, Steilacoom | 1535-4, Stillaguamish | 1536-2, Suquamish | 1537-0, Swinomish | 1538-8, Tulalip | 1539-6, Upper Skagit | 1552-9, Iowa Sac and Fox | 1553-7, Missouri Sac and Fox | 1554-5, Oklahoma Sac and Fox | 1567-7, Big Cypress | 1568-5, Brighton | 1569-3, Florida Seminole | 1570-1, Hollywood Seminole | 1571-9, Oklahoma Seminole | 1574-3, San Manual | 1579-2, Absentee Shawnee | 1580-0, Eastern Shawnee | 1587-5, Battle Mountain | 1588-3, Duckwater | 1589-1, Elko | 1590-9, Ely | 1591-7, Goshute | 1592-5, Panamint | 1593-3, Ruby Valley | 1594-1, Skull Valley | 1595-8, South Fork Shoshone | 1596-6, Te-Moak Western Shoshone | 1597-4, Timbi-Sha Shoshone | 1598-2, Washakie | 1599-0, Wind River Shoshone | 1600-6, Yomba | 1603-0, Duck Valley | 1604-8, Fallon | 1605-5, Fort McDermitt | 1610-5, Blackfoot Sioux | 1611-3, Brule Sioux | 1612-1, Cheyenne River Sioux | 1613-9, Crow Creek Sioux | 1614-7, Dakota Sioux | 1615-4, Flandreau Santee | 1616-2, Fort Peck | 1617-0, Lake Traverse Sioux | 1618-8, Lower Brule Sioux | 1619-6, Lower Sioux | 1620-4, Mdewakanton Sioux | 1621-2, Miniconjou | 1622-0, Oglala Sioux | 1623-8, Pine Ridge Sioux | 1624-6, Pipestone Sioux | 1625-3, Prairie Island Sioux | 1626-1, Prior Lake Sioux | 1627-9, Rosebud Sioux | 1628-7, Sans Arc Sioux | 1629-5, Santee Sioux | 1630-3, Sisseton-Wahpeton | 1631-1, Sisseton Sioux | 1632-9, Spirit Lake Sioux | 1633-7, Standing Rock Sioux | 1634-5, Teton Sioux | 1635-2, Two Kettle Sioux | 1636-0, Upper Sioux | 1637-8, Wahpekute Sioux | 1638-6, Wahpeton Sioux | 1639-4, Wazhaza Sioux | 1640-2, Yankton Sioux | 1641-0, Yanktonai Sioux | 1654-3, Ak-Chin | 1655-0, Gila Bend | 1656-8, San Xavier | 1657-6, Sells | 1668-3, Cow Creek Umpqua | 1671-7, Allen Canyon | 1672-5, Uintah Ute | 1673-3, Ute Mountain Ute | 1680-8, Gay Head Wampanoag | 1681-6, Mashpee Wampanoag | 1688-1, Alpine | 1689-9, Carson | 1690-7, Dresslerville | 1697-2, Ho-chunk | 1698-0, Nebraska Winnebago | 1705-3, Table Bluff | 1712-9, Barrio Libre | 1713-7, Pascua Yaqui | 1718-6, Chukchansi | 1719-4, Tachi | 1720-2, Tule River | 1725-1, Cocopah | 1726-9, Havasupai | 1727-7, Hualapai | 1728-5, Maricopa | 1729-3, Mohave | 1730-1, Quechan | 1731-9, Yavapai | 1733-5, Coast Yurok | 1737-6, Alaska Indian | 1840-8, Eskimo | 1966-1, Aleut | 1739-2, Alaskan Athabascan | 1811-9, Southeast Alaska | 1740-0, Ahtna | 1741-8, Alatna | 1742-6, Alexander | 1743-4, Allakaket | 1744-2, Alanvik | 1745-9, Anvik | 1746-7, Arctic | 1747-5, Beaver | 1748-3, Birch Creek | 1749-1, Cantwell | 1750-9, Chalkyitsik | 1751-7, Chickaloon | 1752-5, Chistochina | 1753-3, Chitina | 1754-1, Circle | 1755-8, Cook Inlet | 1756-6, Copper Center | 1757-4, Copper River | 1758-2, Dot Lake | 1759-0, Doyon | 1760-8, Eagle | 1761-6, Eklutna | 1762-4, Evansville | 1763-2, Fort Yukon | 1764-0, Gakona | 1765-7, Galena | 1766-5, Grayling | 1767-3, Gulkana | 1768-1, Healy Lake | 1769-9, Holy Cross | 1770-7, Hughes | 1771-5, Huslia | 1772-3, Iliamna | 1773-1, Kaltag | 1774-9, Kluti Kaah | 1775-6, Knik | 1776-4, Koyukuk | 1777-2, Lake Minchumina | 1778-0, Lime | 1779-8, Mcgrath | 1780-6, Manley Hot Springs | 1781-4, Mentasta Lake | 1782-2, Minto | 1783-0, Nenana | 1784-8, Nikolai | 1785-5, Ninilchik | 1786-3, Nondalton | 1787-1, Northway | 1788-9, Nulato | 1789-7, Pedro Bay | 1790-5, Rampart | 1791-3, Ruby | 1792-1, Salamatof | 1793-9, Seldovia | 1794-7, Slana | 1795-4, Shageluk | 1796-2, Stevens | 1797-0, Stony River | 1798-8, Takotna | 1799-6, Tanacross | 1800-2, Tanaina | 1801-0, Tanana | 1802-8, Tanana Chiefs | 1803-6, Tazlina | 1804-4, Telida | 1805-1, Tetlin | 1806-9, Tok | 1807-7, Tyonek | 1808-5, Venetie | 1809-3, Wiseman | 1813-5, Tlingit-Haida | 1837-4, Tsimshian | 1814-3, Angoon | 1815-0, Central Council of Tlingit and Haida Tribes | 1816-8, Chilkat | 1817-6, Chilkoot | 1818-4, Craig | 1819-2, Douglas | 1820-0, Haida | 1821-8, Hoonah | 1822-6, Hydaburg | 1823-4, Kake | 1824-2, Kasaan | 1825-9, Kenaitze | 1826-7, Ketchikan | 1827-5, Klawock | 1828-3, Pelican | 1829-1, Petersburg | 1830-9, Saxman | 1831-7, Sitka | 1832-5, Tenakee Springs | 1833-3, Tlingit | 1834-1, Wrangell | 1835-8, Yakutat | 1838-2, Metlakatla | 1842-4, Greenland Eskimo | 1844-0, Inupiat Eskimo | 1891-1, Siberian Eskimo | 1896-0, Yupik Eskimo | 1845-7, Ambler | 1846-5, Anaktuvuk | 1847-3, Anaktuvuk Pass | 1848-1, Arctic Slope Inupiat | 1849-9, Arctic Slope Corporation | 1850-7, Atqasuk | 1851-5, Barrow | 1852-3, Bering Straits Inupiat | 1853-1, Brevig Mission | 1854-9, Buckland | 1855-6, Chinik | 1856-4, Council | 1857-2, Deering | 1858-0, Elim | 1859-8, Golovin | 1860-6, Inalik Diomede | 1861-4, Inupiaq | 1862-2, Kaktovik | 1863-0, Kawerak | 1864-8, Kiana | 1865-5, Kivalina | 1866-3, Kobuk | 1867-1, Kotzebue | 1868-9, Koyuk | 1869-7, Kwiguk | 1870-5, Mauneluk Inupiat | 1871-3, Nana Inupiat | 1872-1, Noatak | 1873-9, Nome | 1874-7, Noorvik | 1875-4, Nuiqsut | 1876-2, Point Hope | 1877-0, Point Lay | 1878-8, Selawik | 1879-6, Shaktoolik | 1880-4, Shishmaref | 1881-2, Shungnak | 1882-0, Solomon | 1883-8, Teller | 1884-6, Unalakleet | 1885-3, Wainwright | 1886-1, Wales | 1887-9, White Mountain | 1888-7, White Mountain Inupiat | 1889-5, Mary's Igloo | 1892-9, Gambell | 1893-7, Savoonga | 1894-5, Siberian Yupik | 1897-8, Akiachak | 1898-6, Akiak | 1899-4, Alakanuk | 1900-0, Aleknagik | 1901-8, Andreafsky | 1902-6, Aniak | 1903-4, Atmautluak | 1904-2, Bethel | 1905-9, Bill Moore's Slough | 1906-7, Bristol Bay Yupik | 1907-5, Calista Yupik | 1908-3, Chefornak | 1909-1, Chevak | 1910-9, Chuathbaluk | 1911-7, Clark's Point | 1912-5, Crooked Creek | 1913-3, Dillingham | 1914-1, Eek | 1915-8, Ekuk | 1916-6, Ekwok | 1917-4, Emmonak | 1918-2, Goodnews Bay | 1919-0, Hooper Bay | 1920-8, Iqurmuit (Russian Mission) | 1921-6, Kalskag | 1922-4, Kasigluk | 1923-2, Kipnuk | 1924-0, Koliganek | 1925-7, Kongiganak | 1926-5, Kotlik | 1927-3, Kwethluk | 1928-1, Kwigillingok | 1929-9, Levelock | 1930-7, Lower Kalskag | 1931-5, Manokotak | 1932-3, Marshall | 1933-1, Mekoryuk | 1934-9, Mountain Village | 1935-6, Naknek | 1936-4, Napaumute | 1937-2, Napakiak | 1938-0, Napaskiak | 1939-8, Newhalen | 1940-6, New Stuyahok | 1941-4, Newtok | 1942-2, Nightmute | 1943-0, Nunapitchukv | 1944-8, Oscarville | 1945-5, Pilot Station | 1946-3, Pitkas Point | 1947-1, Platinum | 1948-9, Portage Creek | 1949-7, Quinhagak | 1950-5, Red Devil | 1951-3, St. Michael | 1952-1, Scammon Bay | 1953-9, Sheldon's Point | 1954-7, Sleetmute | 1955-4, Stebbins | 1956-2, Togiak | 1957-0, Toksook | 1958-8, Tulukskak | 1959-6, Tuntutuliak | 1960-4, Tununak | 1961-2, Twin Hills | 1962-0, Georgetown (Yupik-Eskimo) | 1963-8, St. Mary's | 1964-6, Umkumiate | 1968-7, Alutiiq Aleut | 1972-9, Bristol Bay Aleut | 1984-4, Chugach Aleut | 1990-1, Eyak | 1992-7, Koniag Aleut | 2002-4, Sugpiaq | 2004-0, Suqpigaq | 2006-5, Unangan Aleut | 1969-5, Tatitlek | 1970-3, Ugashik | 1973-7, Chignik | 1974-5, Chignik Lake | 1975-2, Egegik | 1976-0, Igiugig | 1977-8, Ivanof Bay | 1978-6, King Salmon | 1979-4, Kokhanok | 1980-2, Perryville | 1981-0, Pilot Point | 1982-8, Port Heiden | 1985-1, Chenega | 1986-9, Chugach Corporation | 1987-7, English Bay | 1988-5, Port Graham | 1993-5, Akhiok | 1994-3, Agdaagux | 1995-0, Karluk | 1996-8, Kodiak | 1997-6, Larsen Bay | 1998-4, Old Harbor | 1999-2, Ouzinkie | 2000-8, Port Lions | 2007-3, Akutan | 2008-1, Aleut Corporation | 2009-9, Aleutian | 2010-7, Aleutian Islander | 2011-5, Atka | 2012-3, Belkofski | 2013-1, Chignik Lagoon | 2014-9, King Cove | 2015-6, False Pass | 2016-4, Nelson Lagoon | 2017-2, Nikolski | 2018-0, Pauloff Harbor | 2019-8, Qagan Toyagungin | 2020-6, Qawalangin | 2021-4, St. George | 2022-2, St. Paul | 2023-0, Sand Point | 2024-8, South Naknek | 2025-5, Unalaska | 2026-3, Unga | 2029-7, Asian Indian | 2030-5, Bangladeshi | 2031-3, Bhutanese | 2032-1, Burmese | 2033-9, Cambodian | 2034-7, Chinese | 2035-4, Taiwanese | 2036-2, Filipino | 2037-0, Hmong | 2038-8, Indonesian | 2039-6, Japanese | 2040-4, Korean | 2041-2, Laotian | 2042-0, Malaysian | 2043-8, Okinawan | 2044-6, Pakistani | 2045-3, Sri Lankan | 2046-1, Thai | 2047-9, Vietnamese | 2048-7, Iwo Jiman | 2049-5, Maldivian | 2050-3, Nepalese | 2051-1, Singaporean | 2052-9, Madagascar | 2056-0, Black | 2058-6, African American | 2060-2, African | 2067-7, Bahamian | 2068-5, Barbadian | 2069-3, Dominican | 2070-1, Dominica Islander | 2071-9, Haitian | 2072-7, Jamaican | 2073-5, Tobagoan | 2074-3, Trinidadian | 2075-0, West Indian | 2061-0, Botswanan | 2062-8, Ethiopian | 2063-6, Liberian | 2064-4, Namibian | 2065-1, Nigerian | 2066-9, Zairean | 2078-4, Polynesian | 2085-9, Micronesian | 2100-6, Melanesian | 2500-7, Other Pacific Islander | 2079-2, Native Hawaiian | 2080-0, Samoan | 2081-8, Tahitian | 2082-6, Tongan | 2083-4, Tokelauan | 2086-7, Guamanian or Chamorro | 2087-5, Guamanian | 2088-3, Chamorro | 2089-1, Mariana Islander | 2090-9, Marshallese | 2091-7, Palauan | 2092-5, Carolinian | 2093-3, Kosraean | 2094-1, Pohnpeian | 2095-8, Saipanese | 2096-6, Kiribati | 2097-4, Chuukese | 2098-2, Yapese | 2101-4, Fijian | 2102-2, Papua New Guinean | 2103-0, Solomon Islander | 2104-8, New Hebrides | 2108-9, European | 2118-8, Middle Eastern or North African | 2129-5, Arab | 2109-7, Armenian | 2110-5, English | 2111-3, French | 2112-1, German | 2113-9, Irish | 2114-7, Italian | 2115-4, Polish | 2116-2, Scottish | 2119-6, Assyrian | 2120-4, Egyptian | 2121-2, Iranian | 2122-0, Iraqi | 2123-8, Lebanese | 2124-6, Palestinian | 2125-3, Syrian | 2126-1, Afghanistani | 2127-9, Israeili | 2131-1, Other Race"
	);

	/**
	 * fhir data container
	 * contains data and errors collected with FHIR
	 *
	 * @var FhirData
	 */
	public $fhirData = null;


	/**
	 * CONSTRUCTOR
	 */
	public function __construct($this_project_id=null, $realtime_webservice_type='CUSTOM')
	{
		// Set project_id for this object
		if ($this_project_id === 0) {
			$this->project_id = $this_project_id;
		} elseif ($this_project_id === null) {
			if (defined("PROJECT_ID")) {
				$this->project_id = 0;
			} else {
				throw new Exception('No project_id provided!');
			}
		} else {
			$this->project_id = $this_project_id;
		}
		// Set the DDP type
		$this->realtime_webservice_type = $realtime_webservice_type;
	}
	
	
	// Obtain array of the curated LOINC code list of labs
	public function getCuratedLoincLabs()
	{
		// Loop through CSV files to build master array
		foreach (self::$fhirMetadataLabListFiles as $this_file)
		{
			$this_file_path = APP_PATH_DOCROOT . "Resources" . DS . "misc" . DS . $this_file;
			if (is_file($this_file_path) && file_exists($this_file_path)) {
				self::addLoincCsvToArray($this_file_path);
			}
		}
	}
	
	
	// Apply list of curated LOINC labs to remove non-essential labs
	private function applyCuratedLoincLabs($metadata_array)
	{
		// Create array list of all curated LOINC labs
		self::getCuratedLoincLabs();
		// Loop through metadata array
		foreach ($metadata_array as $var=>$attr)
		{
			// If not a lab, then keep it
			if ($attr['category'] != 'Laboratory') {
				// Remove non-labs from curated list
				if (isset(self::$fhirMetadataLabList[$var])) {
					unset(self::$fhirMetadataLabList[$var]);
				}
				// Skip and go to next
				continue;
			}
			// If lab doesn't exist in $fhirMetadataLabList, then remove it here
			if (isset(self::$fhirMetadataLabList[$var])) {
				// Use label from curated file
				if (self::$fhirMetadataLabList[$var] != "") {
					$metadata_array[$var]['label'] = self::$fhirMetadataLabList[$var];
				}
				// Remove from $fhirMetadataLabList so we know what's left over
				unset(self::$fhirMetadataLabList[$var]);
			} else {
				// Remove it
				unset($metadata_array[$var]);
			}
		}
		// Add any leftover labs from the curated list that weren't in the master metadata list
		foreach (self::$fhirMetadataLabList as $var=>$label) {
			$metadata_array[$var] = array('field'=>$var, 'label'=>$label, 'description'=>$label, 
										  'temporal'=>1, 'category'=>'Laboratory', 'subcategory'=>'Other Labs', 'identifier'=>0);
		}
		// Return array
		return $metadata_array;
	}
	
	
	// Convert LOINC CSV file to associative array with LOINC code as key and label as value
	private function addLoincCsvToArray($file_path)
	{		
		// Trim the data, just in case
		$data = trim(file_get_contents($file_path));
		// Add CSV string to memory file so we can parse it into an array
		$h = fopen('php://memory', "x+");
		fwrite($h, $data);
		fseek($h, 0);
		// Now read the CSV file into an array
		$csv_headers = null;
		while (($row = fgetcsv($h, 0, ",")) !== false) {
			if (!$csv_headers) {
				$csv_headers = $row;
			} else {
				// If row is completely blank, then skip it
				if (strlen(trim(implode("", $row))) > 0) {
					$loinc = trim($row[0]);
					$label = trim($row[1]);
					self::$fhirMetadataLabList[$loinc] = $label;
				}
			}
		}
		fclose($h);
	}


	/**
	 * RENDER THE REAL-TIME WEB SERVICE SETUP PAGE IN THE PROJECT
	 */
	public function renderSetupPage()
	{
		global $lang, $realtime_webservice_type;
		// Ensure that OpenSSL extension is installed on server
		openssl_loaded(true);
		// Instructions
		$html =	RCView::p(array('style'=>'max-width:800px;'),
                    ($realtime_webservice_type == 'FHIR' ? "{$lang['ws_288']} {$lang['ws_289']} {$lang['ws_54']} " : "{$lang['ws_37']} {$lang['ws_13']} {$lang['ws_54']} ") .
					RCView::a(array('href'=>'javascript:;', 'onclick'=>'ddpExplainDialog('.($realtime_webservice_type == 'FHIR' ? 1 : 0).');', 'style'=>'text-decoration:underline;'), $lang['global_58'])
				);				
		// If a POST request, process the submission and save it
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['map_fields'])) {
			$html .= $this->saveFieldMappings();
		}
		// Display tree of external fields for user to choose prior to mapping
		if ((!$this->isMappingSetUp() && !isset($_POST['select_fields'])) || isset($_GET['add_fields'])) {
			$html .= $this->renderExternalSourceFieldTree();
		}
		// Display table of mappable fields from external source
		if (!isset($_GET['add_fields'])) {
			$html .= $this->renderExternalSourceFieldTable();
		}
		// Return html
		return RCView::div(array('style'=>'max-width:1000px;margin:20px 0;'), $html);
	}


	/**
	 * SAVE MAPPINGS OF EXTERNAL SOURCE FIELDS TO REDCAP FIELDS FROM POST
	 */
	public function saveFieldMappings()
	{
		global $longitudinal, $Proj, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, $lang;

		// Get days offset values, overwrite global variables, and them remove them from Post array
		$realtime_webservice_offset_days = $_POST['rtws_offset_days'];
		$realtime_webservice_offset_plusminus = $_POST['rtws_offset_plusminus'];
		unset($_POST['rtws_offset_days'], $_POST['rtws_offset_plusminus']);

		// Get the external source fields already mapped in this project
		$existing_mappings = $this->getMappedFields();
		// Initially set array as existing mappings and remove them as we go so that in the end only the ones to delete are left
		$mappings_to_delete = $existing_mappings;

		// Process any preview fields designated
		$preview_fields = array();
		$preview_field_counter = 1;
		if (isset($_POST['preview_field'])) {
			// Add to other array
			foreach ($_POST['preview_field'] as $this_preview_field) {
				$preview_fields['field'.$preview_field_counter++] = $this_preview_field;
			}
			// Remove from Post
			unset($_POST['preview_field']);
		}

		// Loop through all Post values and place mappings into array
		$mappings = $mappings_delete = array();
		foreach ($_POST as $key=>$val) {
			// Only look at the checkbox fields of each pair
			if (substr($key, 0, 4) != 'ddf-') continue;
			// Get src field and mapping value
			$src_field = substr($key, 4);
			// Loop through sub-array
			foreach ($val as $key2=>$src_mapping_field) {
				// Is this field the record identifier?
				$is_record_identifier = (isset($_POST['id-'.$src_field][$key2])) ? "1" : "";
				// Get mapping event_id
				if ($longitudinal) {
					$src_mapping_event_id = (isset($_POST['dde-'.$src_field][$key2])) ? $_POST['dde-'.$src_field][$key2] : "";
					if (empty($src_mapping_event_id)) $src_mapping_event_id = $Proj->firstEventId;
				} else {
					$src_mapping_event_id = $Proj->firstEventId;
				}
				// Get mapped temporal field (if the src field is temporal)
				$rc_temporal_field = (isset($_POST['ddt-'.$src_field][$key2])) 
								? $_POST['ddt-'.$src_field][$key2] 
								: ((isset($_POST['ddt-'.$src_field][0])) ? $_POST['ddt-'.$src_field][0] : "");
				// Get mapped pre-selection option (if the src field is temporal)
				$rc_preselect = (isset($_POST['ddp-'.$src_field][$key2]) && $_POST['ddp-'.$src_field][$key2] != 'NULL')
								? $_POST['ddp-'.$src_field][$key2] 
								: ((isset($_POST['ddp-'.$src_field][0]) && $_POST['ddp-'.$src_field][0] != 'NULL') ? $_POST['ddp-'.$src_field][0] : "");
				if ($src_mapping_field != "") {
					// Determine if already exists in table
					$this_action = (isset($existing_mappings[$src_field][$src_mapping_event_id][$src_mapping_field])) ? 'update' : 'insert';
					// Add to array for saving in table
					$mappings[$src_field][] = array('field_name'=>$src_mapping_field, 'event_id'=>$src_mapping_event_id,
													'is_record_identifier'=>$is_record_identifier, 'temporal_field'=>$rc_temporal_field,
													'preselect'=>$rc_preselect, 'action'=>$this_action);
					// Remove from "delete" array
					unset($mappings_to_delete[$src_field][$src_mapping_event_id][$src_mapping_field]);
				}
			}
		}
		## Now find ones that need to be deleted
		// Loop through $mappings_to_delete and remove empty arrays so we can tell what's really left
		$mappings_to_delete_mapids = array();
		foreach ($mappings_to_delete as $src_field=>$event_array) {
			foreach ($event_array as $this_event_id=>$field_array) {
				if (empty($field_array)) unset($mappings_to_delete[$src_field][$this_event_id]);
			}
			if (empty($mappings_to_delete[$src_field])) unset($mappings_to_delete[$src_field]);
		}
		// Get map_id's of mappings to delete
		foreach ($mappings_to_delete as $src_field=>$event_array) {
			foreach ($event_array as $this_event_id=>$field_array) {
				foreach ($field_array as $src_mapping_field=>$attr) {
					// Add map_id
					$mappings_to_delete_mapids[] = $attr['map_id'];
				}
			}
		}
		// print_array($existing_mappings);
		// print_array($mappings);

		## SAVE MAPPINGS
		// Set "identifier" col to 0 for all to prevent key constraint error as we go updating rows
		$sql = "update redcap_ddp_mapping set is_record_identifier = null where project_id = ".$this->project_id;
		$q = db_query($sql);
		// Now go through and delete any map_ids ready to delete
		if (!empty($mappings_to_delete_mapids)) {
			$sql = "delete from redcap_ddp_mapping where map_id in (" . prep_implode($mappings_to_delete_mapids) . ")";
			$q = db_query($sql);
		}
		// Now loop through mappings and insert/update each
		foreach ($mappings as $src_field=>$attr_array) {
			foreach ($attr_array as $attr) {
				if ($attr['action'] == 'insert') {
					// Insert
					$sql = "insert into redcap_ddp_mapping (project_id, field_name, event_id, external_source_field_name,
							is_record_identifier, temporal_field, preselect)
							values (".$this->project_id.", '".db_escape($attr['field_name'])."', '".db_escape($attr['event_id'])."',
							'".db_escape($src_field)."', ".checkNull($attr['is_record_identifier']).",
							".checkNull($attr['temporal_field']).", ".checkNull($attr['preselect']).")";
				} else {
					// Update
					$sql = "update redcap_ddp_mapping
							set	is_record_identifier = ".checkNull($attr['is_record_identifier']).",
							temporal_field = ".checkNull($attr['temporal_field']).", preselect = ".checkNull($attr['preselect'])."
							where project_id = ".$this->project_id." and field_name = '".db_escape($attr['field_name'])."'
							and event_id = '".db_escape($attr['event_id'])."' and external_source_field_name = '".db_escape($src_field)."'";
				}
				$q = db_query($sql);
				// if (db_error() != "") print "<br><b>MySQL error " . db_errno() . ":</b><br>" . db_error() . "<br><br><b>Failed query:</b><br>$sql";
			}
		}

		// Add day offset defaults to redcap_projects
		$sql = "update redcap_projects set realtime_webservice_offset_days = '".db_escape($realtime_webservice_offset_days)."',
				realtime_webservice_offset_plusminus = '".db_escape($realtime_webservice_offset_plusminus)."' where project_id = ".$this->project_id;
		$q = db_query($sql);

		## Preview Fields: Add to table
		// First, remove from table
		$sql = "delete from redcap_ddp_preview_fields where project_id = ".$this->project_id;
		$q = db_query($sql);
		// Now re-add to table if existed before
		if (!empty($preview_fields)) {
			// Remove row from table
			$sql = "insert into redcap_ddp_preview_fields (project_id, ".implode(", ", array_keys($preview_fields)).")
					values (".$this->project_id.", ".prep_implode($preview_fields).")";
			$q = db_query($sql);
		}

		// Now reset the field mapping array now that it's been modified
		$this->field_mappings = null;

		// Log this event
		$loggedValues = json_encode(array('offset_days'=>$realtime_webservice_offset_days, 'offset_plusminus'=>$realtime_webservice_offset_plusminus,
							'preview_fields'=>$preview_fields, 'mappings'=>$mappings));
		Logging::logEvent("", "redcap_ddp_mapping", "MANAGE", $this->project_id, $loggedValues, "Map source fields (DDP)");

		## RETURN "SAVED" MSG
		return 	RCView::div(array('class'=>'darkgreen msgrt', 'style'=>'text-align:center;margin:20px 0;'),
					RCView::img(array('src'=>'tick.png')) .
					$lang['ws_100']
				);
	}


	/**
	 * RENDER THE TREE OF EXTERNAL FIELDS FOR USER TO CHOOSE PRIOR TO MAPPING
	 */
	public function renderExternalSourceFieldTree()
	{
		global $lang;

		// HTML
		$html = '';

		// Get the external source fields in array format
		$external_fields_all = $this->getExternalSourceFields();

		// Get the external id field name
		$external_id_field = '';
		foreach ($external_fields_all as $attr) {
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$external_id_field = $attr['field'];
			}
		}

		// Get the external source fields already mapped in this project
		$external_fields_mapped = $this->getMappedFields();

		// Javascript
		?>
		<script type="text/javascript">
		// Get the external id field name
		var external_id_field = 'ddp_datamart_fields[<?php echo js_escape($external_id_field) ?>]';
		</script>
		<?php
		// Call javascript file
		loadJS('DynamicDataPullMapping.js');

		## Collect categories and subcategories into an array
		$categories = array();
		foreach ($external_fields_all as $key=>$attr)
		{
			// Set array key as the field name so we can find their attributes faster
			unset($external_fields_all[$key]);
			$external_fields_all[$attr['field']] = $attr;

			// For those not in a category
			if ($attr['category'] == '') {
				$categories[''][] = $attr['field'];
			}
			// For those in a category
			else {
				if (!isset($categories[$attr['category']])) $categories[$attr['category']] = array();
				if ($attr['subcategory'] != '') {
					// Add to subcategory
					if (!isset($categories[$attr['category']][$attr['subcategory']])) $categories[$attr['category']][$attr['subcategory']] = array();
					$categories[$attr['category']][$attr['subcategory']][] = $attr['field'];
				} else {
					// Add to category
					$categories[$attr['category']][''][] = $attr['field'];
				}
			}
		}

		//print_array($categories);

		// Loop through categories and subcats and build li/ul html
		$tree = '';
		$num_fields_checked = 0;
		$uniqueFields = array();
		foreach ($categories as $cat=>$catattr)
		{
			$cat_html = '';
			$num_fields_checked_cat = 0;
			// If no cat
			if ($cat == '') {
				// Loop through fields
				foreach ($catattr as $this_field) {
					$checked = '';
					$div_color = '';
					if ($this_field == $external_id_field || isset($external_fields_mapped[$this_field])) {
						$checked = 'checked';
						$num_fields_checked++;
						$num_fields_checked_cat++;
						// Set color for source id field
						if ($this_field == $external_id_field) $div_color = 'font-weight:bold;color:#C00000;';
					}
					if (isset($uniqueFields[$this_field])) {
						$uniqueFields[$this_field]++;
					} else {
						$uniqueFields[$this_field] = 1;
					}
					$cat_html .= RCView::div(array('class'=>'extsrcfld', 'style'=>$div_color),
									RCView::checkbox(array('name'=>"ddp_datamart_fields[$this_field]", 'data-field'=>$this_field, 'oid'=>$uniqueFields[$this_field], 'style'=>'vertical-align:middle;', $checked=>$checked)) .
									$this_field . " (" . $external_fields_all[$this_field]['label'] . ")" . RCView::SP .
									($external_fields_all[$this_field]['description'] == '' ? '' :
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$external_fields_all[$this_field]['description']), '?')
									)
								 );
				}
			}
			// If in a cat
			else {
				// Loop through subcats
				$subcat_html = '';
				// Count subcats
				$num_subcats = 0;
				foreach ($catattr as $subcat=>$subcatattr) {
					// Loop through fields
					$subcat_fields_html = '';
					$num_fields_checked_subcat = 0;
					foreach ($subcatattr as $this_field) {
						$checked = '';
						$div_color = '';
						if ($this_field == $external_id_field || isset($external_fields_mapped[$this_field])) {
							$checked = 'checked';
							$num_fields_checked++;
							$num_fields_checked_cat++;
							$num_fields_checked_subcat++;
							// Set color for source id field
							if ($this_field == $external_id_field) $div_color = 'font-weight:bold;color:#C00000;';
						}
						if (isset($uniqueFields[$this_field])) {
							$uniqueFields[$this_field]++;
						} else {
							$uniqueFields[$this_field] = 1;
						}
						$subcat_fields_html .= 	RCView::div(array('class'=>'extsrcfld', 'style'=>$div_color),
													RCView::checkbox(array('name'=>"ddp_datamart_fields[$this_field]", 'data-field'=>$this_field, 'oid'=>$uniqueFields[$this_field], 'style'=>'vertical-align:middle;', $checked=>$checked)) .
													$this_field . " (" . $external_fields_all[$this_field]['label'] . ") " . RCView::SP .
													($external_fields_all[$this_field]['description'] == '' ? '' :
														RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$external_fields_all[$this_field]['description']), '?')
													)
												);
					}
					//print "CAT: $cat, SUBCAT: $subcat<br>$subcat_fields_html";
					// If no subcat
					if ($subcat != '') {
						$num_subcats++;
						$displaySubcat = ($num_fields_checked_subcat == 0) ? "" : "display:block;";
						$displaySubcatSelectAll = ($num_fields_checked_subcat == 0) ? "display:none;" : "";
						$subcatIcon = ($num_fields_checked_subcat == 0) ? 'expand.png' : 'collapse.png';
						$subcat_html .= RCView::div(array('class'=>'rc_ext_subcat_name'),
											RCView::img(array('src'=>$subcatIcon, 'style'=>'vertical-align:middle;')) .
											RCView::a(array('href'=>'javascript:;'), $subcat) .
											// Display "select all" link
											RCView::a(array('href'=>'javascript:;', 'class'=>'selectalllink', 'style'=>$displaySubcatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;margin-left:25px;'), $lang['ws_35']) .
											RCView::span(array('class'=>'selectalllink_sep', 'style'=>$displaySubcatSelectAll.'color:#aaa;margin:0 5px;'), "|") .
											// Display "deselect all" link
											RCView::a(array('href'=>'javascript:;', 'class'=>'deselectalllink', 'style'=>$displaySubcatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;'), $lang['ws_55'])
										) .
										RCView::div(array('class'=>'rc_ext_subcat', 'style'=>$displaySubcat), $subcat_fields_html);
					} else {
						$subcat_html .= $subcat_fields_html;
					}
				}
				// Render this cat
				$displayCat = ($num_fields_checked_cat == 0) ? "" : "display:block;";
				$displayCatSelectAll = ($num_fields_checked_subcat == 0) ? "display:none;" : "";
				$catIcon = ($num_fields_checked_cat == 0) ? 'expand.png' : 'collapse.png';
				$cat_html .= RCView::div(array('class'=>'rc_ext_cat_name'),
								RCView::img(array('src'=>$catIcon, 'style'=>'vertical-align:middle;')) .
								RCView::a(array('href'=>'javascript:;'), $cat) .
								// If cat does not have subcats, then display "select all" link
								RCView::a(array('href'=>'javascript:;', 'class'=>'selectalllink', 'style'=>$displayCatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;margin-left:25px;'), $lang['ws_35']) .
								RCView::span(array('class'=>'selectalllink_sep', 'style'=>$displayCatSelectAll.'color:#aaa;margin:0 5px;'), "|") .
								// If cat does not have subcats, then display "deselect all" link
								RCView::a(array('href'=>'javascript:;', 'class'=>'deselectalllink', 'style'=>$displayCatSelectAll.'font-size:11px;text-decoration:underline;font-weight:normal;'), $lang['ws_55'])
							 ) .
							 RCView::div(array('class'=>'rc_ext_cat', 'style'=>$displayCat), $subcat_html);
			}
			// Add cat to tree
			$tree .= $cat_html;
		}

		// Set search textbox for search input
		$searchTextbox = 	RCView::span(array('style'=>'margin:0 0 5px;text-align:right;'),
								"Filter:" .
								RCView::text(array('id'=>'source_field_search', 'class'=>'x-form-text x-form-field', 'onkeydown'=>"if(event.keyCode == 13) return false;",
									'style'=>'margin-left:5px;width:300px;color:#999;', 'value'=>$lang['ws_07'],
									'onfocus'=>"if ($(this).val() == '".js_escape($lang['ws_07'])."') { $(this).val(''); $(this).css('color','#000'); }",
									'onblur'=>"$(this).val( trim($(this).val()) ); if ($(this).val() == '') { $(this).val('".js_escape($lang['ws_07'])."'); $(this).css('color','#999'); }"
								))
							);

		// Render the div container for the tree
		$html .= RCView::div(array('id'=>'ext_field_tree'),
					($this->project_id === 0 ? "<div>" : "<form id='select_mapping_fields' method='post' action='".PAGE_FULL."?pid=".$this->project_id."' enctype='multipart/form-data'>") .
					($this->project_id === 0 
					? 
						RCView::div(array('class'=>'blue', 'style'=>''),
							RCView::div(array('style'=>'margin:5px 0 0;'),
								RCView::div(array('style'=>'float:left;vertical-align:middle;font-weight:bold;font-size:15px;'), "Source Fields List") .
								RCView::div(array('style'=>'float:right;vertical-align:middle;padding-bottom:2px;'), $searchTextbox) .
								RCView::div(array('class'=>'clear'), '')
							)
						)
					:
						RCView::div(array('style'=>'font-size:15px;font-weight:bold;padding-bottom:8px;margin-bottom:8px;display:flex;'),
							$lang['ws_101'].
							RCView::a(array('href'=>APP_PATH_WEBROOT.'index.php?pid='.PROJECT_ID.'&route=FhirMappingHelperController:index#/','target'=>'_self','class'=>'btn btn-info btn-sm text-white ml-auto'),
							'<i class="fas fa-code-branch"></i> Use the Mapping Helper')
						) .
						RCView::div(array('style'=>'margin-bottom:5px;'), $lang['ws_102']) .
						RCView::div(array('class'=>'blue', 'style'=>'margin:20px 0 0;'),
							$lang['ws_34'] .
							RCView::span(array('id'=>'rc_ext_num_selected2', 'style'=>'font-weight:bold;padding-left:5px;'),
								(empty($external_fields_mapped) ? 1 : count($external_fields_mapped))
							) .
							RCView::button(array('onclick'=>"$('#select_mapping_fields').submit();",
								'class'=>'btn btn-sm btn-primaryrc fs13', 'style'=>'margin-left:125px;'),
								'<i class="fas fa-tasks"></i> '.$lang['ws_33']
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:12px;margin-left:10px;text-decoration:underline;',
								'onclick'=>"window.location.href = app_path_webroot+'".($this->isMappingSetUp() ? "DynamicDataPull/setup.php" : "ProjectSetup/index.php")."?pid=".$this->project_id."';"),
								$lang['global_53']
							) .
							//
							RCView::div(array('style'=>'margin:25px 0 0;'),
								RCView::div(array('style'=>'float:left;vertical-align:middle;font-weight:bold;font-size:15px;'), "Source Fields List") .
								RCView::div(array('style'=>'float:right;vertical-align:middle;padding-bottom:2px;'), $searchTextbox) .
								RCView::div(array('class'=>'clear'), '')
							)
						)
					) .
					RCView::div(array('id'=>'ext_field_tree_fields', 'style'=>'clear:both;padding:10px 6px;background-color:#fff;border:1px solid #ccc;'),
						$tree
					) .
					($this->project_id === 0 ? "" :
						RCView::div(array('class'=>'blue', 'style'=>'margin:0 0 20px;'),
							$lang['ws_34'] .
							RCView::span(array('id'=>'rc_ext_num_selected', 'style'=>'font-weight:bold;padding-left:5px;'),
								count($external_fields_mapped)
							) .
							RCView::hidden(array('name'=>'select_fields', 'value'=>'1')) .
							RCView::button(array('onclick'=>"$('#select_mapping_fields').submit();",
								'class'=>'btn btn-sm btn-primaryrc fs13', 'style'=>'margin-left:125px;'),
								'<i class="fas fa-tasks"></i> '.$lang['ws_32']
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:10px;text-decoration:underline;',
								'onclick'=>"window.location.href = app_path_webroot+'".($this->isMappingSetUp() ? "DynamicDataPull/setup.php" : "ProjectSetup/index.php")."?pid=".$this->project_id."';"),
								$lang['global_53']
							)
						)
					) .
					($this->project_id === 0 ? "</div>" : "</form>")
				 );

		// Return html
		return $html;
	}


	/**
	 * RENDER THE TABLE FOR MAPPING EXTERNAL SOURCE FIELDS
	 */
	private function renderExternalSourceFieldTable()
	{
		// Get global vars
		global $Proj, $lang, $longitudinal, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus;

		// If fields are not mapped AND we've not just selected some to map, then don't display table
		if (!$this->isMappingSetUp() && !isset($_POST['select_fields'])) return '';

		// Call javascript file
		loadJS('DynamicDataPullMapping.js');

        if (isset($_POST['ddp_datamart_fields'])) {
            // Restructure POST array
            foreach ($_POST['ddp_datamart_fields'] as $this_ext_field=>$is_on) {
                unset($_POST['ddp_datamart_fields'][$this_ext_field]);
                $_POST[$this_ext_field] = $is_on;
            }
            unset($_POST['ddp_datamart_fields']);
        }

		// Get the external source fields in array format
		$external_fields_all = $this->getExternalSourceFields();

		// Now filter out the ones NOT selected
		$external_id_field_attr = array();
		$src_preview_fields_all = array(''=>'');
		$external_id_field = '';
		foreach ($external_fields_all as $key=>$attr) {
			// Remove from original array via its key
			unset($external_fields_all[$key]);
			// If has already been mapped or user just selected fields to map
			if (!isset($_POST['select_fields']) || (isset($_POST['select_fields']) && isset($_POST[$attr['field']]))) {
				// Re-add to array but with field as key
				$external_fields_all[$attr['field']] = $attr;
				// If this is the source identifier field, then store separately and later prepend to array
				if (isset($attr['identifier']) && $attr['identifier'] == '1') {
					$external_id_field_attr[$attr['field']] = $attr;
					$external_id_field = $attr['field'];
				}
			}
			// Add all non-temporal fields to array for Preview feature
			if (!(isset($attr['temporal']) && $attr['temporal'] == '1') && !(isset($attr['identifier']) && $attr['identifier'] == '1')) {
				$src_preview_fields_all[$attr['field']] = "{$attr['field']} \"{$attr['label']}\"";
			}
		}

		// SORT SOURCE FIELDS: Although the fields will already be sorted within their subcategories, we need to now sort them by their category.
		$srcCatFieldSort = array();
		$keys = array_keys($external_fields_all);
		// Shift off Source ID field, then re-add after sort (to keep in at the beginning)
		$sourceIdArray = array_shift($external_fields_all);
		$nothing = array_shift($keys);
		$external_fields_all = array_combine($keys, $external_fields_all);
		foreach ($external_fields_all as $key=>$attr) {
			// Now add category+field to sorting array
			$srcCatFieldSort[] = $attr['category'].'---'.$attr['field'];
		}
		// Now sort $external_fields_all according to cat+field value
		array_multisort($srcCatFieldSort, SORT_REGULAR, $external_fields_all, $keys);
		$external_fields_all = array_combine($keys, $external_fields_all);
		// Now add Source ID field back to beginning
		$external_fields_all = array($sourceIdArray['field']=>$sourceIdArray) + $external_fields_all;
		unset($srcCatFieldSort);

		// Get the external source fields already mapped in this project
		$external_fields_mapped = $this->getMappedFields();

		## Find any many-to-one mappings (many source fields to same REDCap field)
		// Remove map_id attribute from $external_fields_mapped and put in new array
		$many_to_one_external_fields = $many_to_one_keys = $external_fields_mapped_wo_mapid = array();
		$temporal_field_list = array(''=>'-- Choose other source field --');
		foreach ($external_fields_mapped as $this_ext_field=>$attr1) {
			foreach ($attr1 as $this_event_id=>$these_fields) {
				foreach ($these_fields as $this_field=>$field_attr) {
					// Remove map_id
					unset($field_attr['map_id']);
					// Add to new array
					$external_fields_mapped_wo_mapid[$this_ext_field][$this_event_id][$this_field] = $field_attr;
					// If source field is a temporal field, then add to temporal field list array
					if ($field_attr['temporal_field'] != '') {
						$temporal_field_list[$this_ext_field] = $this_ext_field." \"{$external_fields_all[$this_ext_field]['label']}\"";
					}
				}
			}
		}
		// Now loop through post fields (if just selected fields from the tree) to add them to temporal field list
		if (isset($_POST['select_fields'])) {
			foreach ($_POST as $this_ext_field=>$is_on) {
				if ($this_ext_field == 'select_fields' || $is_on != 'on') continue;
				if ($external_fields_all[$this_ext_field]['temporal']) {
					$temporal_field_list[$this_ext_field] = $this_ext_field." \"{$external_fields_all[$this_ext_field]['label']}\"";
				}
			}
		}

		// Sort $temporal_field_list alphabetically by source variable name
		ksort($temporal_field_list);
		// Now loop through the fields again in $external_fields_mapped_wo_mapid and note any with same exact mappings (i.e. are many-to-one)
		$i = 0;
		foreach ($external_fields_mapped_wo_mapid as $this_ext_field=>$this_attr) {
			// Loop through the entire mapping AGAIN (ignoring this_ext_field) so that we loop through EVERY field for EACH field
			foreach ($external_fields_mapped_wo_mapid as $this_loop_ext_field=>$this_loop_attr) {
				// Skip if same field
				if ($this_ext_field != $this_loop_ext_field && $this_attr === $this_loop_attr) {
					// Serialize array and make it the array key with the source fields as sub-array values (best way to group them)
					$key_serialized = serialize($this_attr);
					if (!isset($many_to_one_external_fields[$key_serialized])) {
						// If the first field to have the same attributes, then put this one field in array to note to us where to begin
						$many_to_one_keys[$this_ext_field] = $key_serialized;
					}
					// Add to array
					$many_to_one_external_fields[$key_serialized][] = $this_ext_field;
					// Keep the subarrays unique
					$many_to_one_external_fields[$key_serialized] = array_values(array_unique($many_to_one_external_fields[$key_serialized]));
				}
			}
		}
		
		// Loop through fields once more, and as we loop, if any fields are many-to-one and should be grouped together,
		// then remove from array and resplice them together in array
		$mapped_wo_mapid_temp = $mapped_wo_mapid_ignore = array();
		// Copy array to rework it as we go
		$external_fields_all_regrouped = $external_fields_all;
		foreach ($external_fields_mapped_wo_mapid as $this_ext_field=>$this_attr) {
			// Does this field exist in the $many_to_one_keys array?
			if (!isset($many_to_one_keys[$this_ext_field])) continue;
			// If so, get serialized key to connect it to the fields in array $many_to_one_external_fields
			$these_many_to_one_fields = $many_to_one_external_fields[$many_to_one_keys[$this_ext_field]];
			// Shift off first field in array
			$this_first_many_to_one = array_shift($these_many_to_one_fields);
			// Add group fields to new array with attributes
			$these_many_to_one_fields_attr = array($this_first_many_to_one=>$external_fields_all[$this_first_many_to_one]);
			// Loop through other fields in this group and remove them from $external_fields_all_regrouped
			// (because we'll add them back later in the correct position)
			foreach ($these_many_to_one_fields as $this_other_many_to_one) {
				// Remove from array
				unset($external_fields_all_regrouped[$this_other_many_to_one]);
				// Add field to field group array containing attributes
				$these_many_to_one_fields_attr[$this_other_many_to_one] = $external_fields_all[$this_other_many_to_one];
			}
			// Get the array position of the first field in $these_many_to_one_fields so we know where to splice $external_fields_mapped_wo_mapid
			$key_position = array_search($this_first_many_to_one, array_keys($external_fields_all_regrouped));
			if ($key_position !== false && !empty($these_many_to_one_fields_attr)) {
				$first_half  = array_slice($external_fields_all_regrouped, 0, $key_position, true);
				$second_half = array_slice($external_fields_all_regrouped, $key_position+1, null, true);
				// Add all 3 array segments back together and PUT MANY-TO-ONE FIELDS AT END
				$external_fields_all_regrouped = $first_half + $these_many_to_one_fields_attr + $second_half;
			}
		}
		//print "<br>count: ".count($external_fields_all);
		//print "<br>count: ".count($external_fields_all_regrouped);
		// print_array(array_keys($external_fields_all_regrouped));
		//print_array(array_diff(array_keys($external_fields_all), array_keys($external_fields_all_regrouped)));
		// Replace original array with the resorted one
		$external_fields_all = $external_fields_all_regrouped;
		// Remove unneeded arrays
		unset($external_fields_all_regrouped);
		
		// Ensure that all non-temporal items are front-loaded and all temporal fields occur last
		$external_fields_all_nontemp = $external_fields_all_temp = array();
		foreach ($external_fields_all as $key=>$attr) {
			if ($attr['temporal']) {
				$external_fields_all_temp[$key] = $attr;
			} else {
				$external_fields_all_nontemp[$key] = $attr;				
			}
		}
		$external_fields_all = $external_fields_all_nontemp + $external_fields_all_temp;
		unset($external_fields_all_nontemp, $external_fields_all_temp);

		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = $rc_date_fields = array(''=>'');
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Ignore SQL fields and Form Status fields
			if ($attr1['element_type'] == 'sql' // || $attr1['element_type'] == 'checkbox'
				|| $attr1['form_name'].'_complete' == $this_field) continue;
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			// If a date field, then add to array
			if ($attr1['element_type'] == 'text' &&
				(in_array($attr1['element_validation_type'], array('date', 'date_ymd', 'date_mdy', 'date_dmy'))
				|| substr($attr1['element_validation_type'], 0, 8) == 'datetime'))
			{
				$rc_date_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			}
		}

		// If longitudinal, build an array of drop-down options listing all REDCap event names
		if ($longitudinal) {
			$rc_events = array(''=>'');
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				// Add to array
				$rc_events[$this_event_id] = $attr['name_ext'];
			}
		}

		$rows = '';

		// Set table headers
		$rows .= RCView::tr('',
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:200px;font-size:13px;'),
						$lang['ws_103'] .
						RCView::div(array('style'=>'color:#800000;'),
							"(" . self::getSourceSystemName() . ")"
						)
					) .
					(!$longitudinal ? '' :
						RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:170px;font-size:13px;'),
							$lang['ws_104']
						)
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;font-size:13px;'),
						$lang['ws_105']
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px;width:200px;font-size:13px;'),
						RCView::div(array('class'=>'wrap'),
							$lang['ws_106']
						) .
						RCView::div(array('style'=>'margin-top:5px;font-weight:normal;color:#777;line-height:10px;font-size:11px;'),
							$lang['ws_107']
						)
					) .
					RCView::td(array('class'=>'header', 'style'=>'padding:10px 5px;text-align:center;font-weight:normal;width:60px;line-height:10px;font-size:11px;'),
						$lang['ws_108']
					)
				);


		// print_array($external_fields_mapped);
		// print_array($external_fields_all);
		// print_array($_POST);

		// Loop through all fields to create each as a table row
		$prev_src_field = $prev_mapped_to_rc_event = $prev_src_cat = $prev_src_subcat = $true_prev_src_field = ''; // defaults
		$nonDisplayedManyToOneMappingsJs = '';
		$catsAlreadyDisplayed = array();
		foreach ($external_fields_all as $src_field=>$attr)
		{
			$src_label  = $attr['label'];
			$src_cat    = $attr['category'];

			// Field neither mapped nor selected from source field tree (do NOT display)
			if (!isset($external_fields_mapped[$src_field]) && !isset($_POST['select_fields'])) {
				continue;
			}

			// Is this field already mapped? If so, put REDCap fields mapped to this source field into array for looping
			if (isset($external_fields_mapped[$src_field])) {
				// Set flag if part of a many-to-one group (but is not the first field)
				$isManyToOne = (isset($external_fields_mapped_wo_mapid[$true_prev_src_field])
					&& $external_fields_mapped_wo_mapid[$src_field] === $external_fields_mapped_wo_mapid[$true_prev_src_field]);
				// Add mappings to array that we will loop through
				$external_fields_mapped_field_list = $external_fields_mapped[$src_field];
				// If temporary fields in the mappings array have the same source field and RC field BUT do NOT have same temporal date field,
				// then separate into multiple arrays so that they don't get bunched together and thus overwrite the date field selection.
				$external_fields_mapped_field_list_super = array();
				$super_key = 0;
				foreach ($external_fields_mapped_field_list as $mapped_to_rc_event=>$mapped_to_rc_event_array) {
					// Set defaults
					$prev_temporal_field = $prev_temporal_preselect = '';
					foreach ($mapped_to_rc_event_array as $mapped_to_rc_field=>$mapped_attr) {
						// If has different temporal field or preselect option, then add as new table row
						if ($prev_temporal_field != $mapped_attr['temporal_field'] || $prev_temporal_preselect != $mapped_attr['preselect']) {
							$super_key++;
						}
						// Add to super array
						$external_fields_mapped_field_list_super[$super_key][$mapped_to_rc_event][$mapped_to_rc_field] = $mapped_attr;
						// Set for next loop
						$prev_temporal_field = $mapped_attr['temporal_field'];
						$prev_temporal_preselect = $mapped_attr['preselect'];
					}
					// Increment super key
					$super_key++;
				}
			} else {
				// New fields to be added
				$external_fields_mapped_field_list_super = array(array(''=>array($src_field=>array())));
				$isManyToOne = false;
			}

//            print_array($_POST);
//            print_array($external_fields_mapped_field_list);
//            print_array($external_fields_mapped_field_list_super);

			// Loop through all REDCap fields mapped to this one source field (one-to-many relationship)
			foreach ($external_fields_mapped_field_list_super as $external_fields_mapped_field_list)
			{
				$firstEventOfItem = true;
				$prev_temporal_preselect = $prev_temporal_field = '';
				foreach ($external_fields_mapped_field_list as $mapped_to_rc_event=>$mapped_to_rc_event_array)
				{
					// MULTI-EVENT MANY-TO-ONE MAPPING
					// In this instance where there is a many-to-one mapping over multiple events, it's really hard to loop sequentially and
					// get the table to display correctly. So instead, collect the source fields on the non-last events in the mapping,
					// and use jQuery to display them after the table is loaded. Not perfect but it works.
					if ($firstEventOfItem &&
						// Is field a many-to-one parent?
						isset($many_to_one_keys[$src_field]) &&
						// Is many-to-one parent used in multiple events?
						count($external_fields_mapped_field_list) > 1)
					{
						// Build arrays of events/fields to loop through in order to construct the javascript
						$theseManyToOneFieldsNonLastEvent = $many_to_one_external_fields[$many_to_one_keys[$src_field]];
						unset($theseManyToOneFieldsNonLastEvent[0]); // Remove first field (not needed)
						$theseManyToOneEvents = array_keys($external_fields_mapped_field_list);
						array_pop($theseManyToOneEvents); // Remove last event_id (not needed)
						// Loop through events/fields to build javascript
						foreach ($theseManyToOneEvents as $thisMtoEventId) {
							foreach ($theseManyToOneFieldsNonLastEvent as $thisMtoField) {
								$nonDisplayedManyToOneMappingsJs .=
									"var addMtoDD = $('table#src_fld_map_table select[name=\"dde-{$src_field}[]\"] option[value=\"$thisMtoEventId\"]:selected').parents('tr:first').find('.manytoone_dd');" .
									"addMtoDD.val('".js_escape($thisMtoField)."');copyRowManyToOneDD(addMtoDD,false);";
							}
						}
					}
					// Skip all non-last events if a many-to-one child row (because they will be added via javascript when page loads)
					if (!$firstEventOfItem && $isManyToOne) {
						continue;
					}

					foreach ($mapped_to_rc_event_array as $mapped_to_rc_field=>$mapped_attr)
					{
						// Is this field already mapped? If so, check the checkbox and highlight as green
						if ($mapped_to_rc_event != '') {
							// Field already mapped
							$highlight_class = "";
							$chkbox_identifier = ($mapped_attr['is_record_identifier'] == '1') ? "checked" : "";
							$mapped_to_rc_date_field = $mapped_attr['temporal_field'];
						} elseif (isset($_POST['select_fields'])) {
							// Field selected from the source field tree
							$chkbox_identifier = $mapped_to_rc_date_field = $mapped_to_rc_field = "";
							// Do green highlight if we just selected new fields to map
							$highlight_class = "blue";
						}

						// If longitudinal, add event drop-down
						$event_dd = "";
						if ($longitudinal) {
							$event_dd = RCView::div(array('style'=>'font-weight:bold;'),
											RCView::select(array('class'=>'evtfld', 'style'=>'max-width:100%;', 'name'=>'dde-'.$src_field.'[]'), $rc_events, $mapped_to_rc_event, 40)
										);
						}

						// If a temporal field, add date field drop-down to select
						$temporal_dd = $deleteFieldLink = $mapAnotherFieldLink = $mapAnotherEventLink = $temporal_field_dd_many_to_one = $copySrcRow = '';
						if ($attr['temporal'] && $src_field != $external_id_field)
						{
							$temporal_dd = 	RCView::div(array(),
												//"Map to REDCap date field:" . RCView::SP . RCView::SP .
												RCView::div(array('style'=>'font-weight:bold;font-size:11px;line-height:8px;'),
													$lang['ws_109']
												) .
												RCView::select(array('class'=>'temfld', 'style'=>'max-width:100%;', 'name'=>'ddt-'.$src_field.'[]'), $rc_date_fields, $mapped_to_rc_date_field, 45) .
												// Preselection option
												RCView::div(array('style'=>'font-weight:bold;font-size:11px;line-height:8px;margin-top:8px;'),
													$lang['ws_110']
												) .
												RCView::select(array('class'=>'presel', 'preval'=>$mapped_attr['preselect'], 'onchange'=>"preselectConform(this);", 'style'=>'max-width:100%;', 'name'=>'ddp-'.$src_field.'[]'),
													array('NULL'=>'-- '.$lang['database_mods_81'].' --', 'MIN'=>$lang['ws_15'],
														  'MAX'=>$lang['ws_16'], 'FIRST'=>$lang['ws_17'], 'LAST'=>$lang['ws_18'],
														  'NEAR'=>$lang['ws_194']), $mapped_attr['preselect'])
											);
							$deleteFieldLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteMapField(this);"),
													RCView::img(array('src'=>'cross_small2.png', 'title'=>$lang['scheduling_57']))
												 );
							$mapAnotherFieldLink = 	RCView::div(array('id'=>'rcAddDD-'.$src_field, 'style'=>'text-align:right;line-height:10px;padding:3px 16px 0 0;'),
														RCView::img(array('src'=>'plus_small2.png')) .
														RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"mapOtherRedcapField(this,'$src_field');"),
															$lang['ws_111']
														)
													);
							$copySrcRow = 	RCView::a(array('title'=>$lang['ws_112'], 'href'=>'javascript:;', 'class'=>'copySrcRowTemporal', 'style'=>'margin-right:5px;', 'onclick'=>"copySrcRowTemporal(this,'$src_field');"),
												RCView::img(array('src'=>'page_copy.png'))
											);
							// Allow to copy row to map src field to fields on another event (except for source id field)
							if ($longitudinal) {
								$mapAnotherEventLink = 	RCView::div(array('style'=>'margin-top:10px;'),
															RCView::img(array('src'=>'plus_small2.png')) .
															RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"copyMappingOtherEvent(this);"),
																$lang['ws_113']
															)
														);
							}
							// Add drop-down of temporal fields for performing many-to-one mappings (only display for first field in many-to-one group)
							//if (!$isManyToOne) {
								// Create drop-down list unique to this source field so that itself is not included in the drop-down
								$this_temporal_field_list = $temporal_field_list;
								unset($this_temporal_field_list[$src_field]);
								// Set link with hidden drop-down
								$temporal_field_dd_many_to_one =
									RCView::div(array(),
										// Preselection option
										RCView::div(array('class'=>'manytoone_showddlink', 'style'=>'margin:10px 0 5px;'),
											RCView::img(array('src'=>'plus_small2.png')) .
											RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"mappingShowManyToOneDD($(this))"),
												$lang['ws_114']
											)
										) .
										RCView::select(array('class'=>'manytoone_dd', 'style'=>'margin-top:8px;display:none;max-width:100%;font-size:11px;', 'onchange'=>'copyRowManyToOneDD($(this));'), $this_temporal_field_list, '', 45)
									);
							//}
						}
						// Set dropdown(s) for REDCap fields to be selected
						$this_rc_field_dropdown = RCView::div(array('style'=>'padding-bottom:2px;white-space:nowrap;'),
													RCView::select(array('class'=>'mapfld', 'onchange'=>($attr['temporal'] ? "preselectConform(this);" : ""), 'style'=>'max-width:94%;', 'name'=>'ddf-'.$src_field.'[]'), $rc_fields, $mapped_to_rc_field, 45) .
													$deleteFieldLink
												  );
						if ($prev_src_field == $src_field && $prev_mapped_to_rc_event == $mapped_to_rc_event
							&& $prev_temporal_field == $mapped_attr['temporal_field'] && $prev_temporal_preselect == $mapped_attr['preselect']) {
							$rc_field_dropdown .= $this_rc_field_dropdown;
						} else {
							$rc_field_dropdown = $this_rc_field_dropdown;
						}
						// Set for next loop
						$prev_src_field = $src_field;
						$prev_mapped_to_rc_event = $mapped_to_rc_event;
						$prev_temporal_field = $mapped_attr['temporal_field'];
						$prev_temporal_preselect = $mapped_attr['preselect'];
					}

					// If this is the source id field, then add a special header
					$external_id_field_special_label = '';
					$external_id_field_special_label_repeating_instance = '';
					$hiddenInputSourceIdMarker = '';
					$removeMappingIcon = RCView::a(array('title'=>$lang['ws_115'], 'class'=>'delMapRow', 'href'=>'javascript:;', 'onclick'=>"deleteMapRow(this);"),
											RCView::img(array('src'=>'cross.png'))
										 );
					if ($src_field == $external_id_field) {
						$external_id_field_special_label = 	RCView::div(array('style'=>'color:#96740E;margin:2px 0 5px;'),
																RCView::img(array('src'=>'star.png')) .
																RCView::span(array('style'=>''), $lang['ws_116'])
															);
						if ($Proj->hasRepeatingFormsEvents()) {
							$external_id_field_special_label_repeating_instance = 
								RCView::div(array('style'=>'color:#777;margin:6px 0 0;font-size:11px;line-height:12px;'), $lang['ws_259']);
						}
						$hiddenInputSourceIdMarker = RCView::hidden(array('name'=>"id-{$external_id_field}[]", 'value'=>'1'));
						$removeMappingIcon = '';
					}

					// If starting a new category, then display cat name as section header
					if ($prev_src_cat != $src_cat && !isset($catsAlreadyDisplayed[$src_cat])
						// If this field is part of a many-to-one group and it is NOT the first field in the group, then do NOT display a cat header
						&& !$isManyToOne
					) {
						$catsAlreadyDisplayed[$src_cat] = true;
						$rows .= RCView::tr(array(),
									RCView::td(array('class'=>'cat_hdr', 'valign'=>'top', 'colspan'=>($longitudinal ? 5 : 4), 'style'=>'font-weight:bold;color:#800000;border:1px solid #aaa;background-color:#ccc;padding:5px 10px;font-size:13px;'),
										$src_cat
									)
								 );
					}

					// print "<hr>$src_field";
					// print_array($external_fields_mapped_wo_mapid[$src_field]);
					// print "<br>$true_prev_src_field";
					// print_array($external_fields_mapped_wo_mapid[$true_prev_src_field]);

					// If a many-to-one child, then add special row attribute to denote this
					$manyToOneAttr = ($isManyToOne ? $src_field : '');
					$manyToOneAttrName = ($isManyToOne ? 'manytoone' : '');

					// Add row
					$rows .= RCView::tr(array($manyToOneAttrName=>$manyToOneAttr),
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class",
									'style'=>'border-bottom:0;padding:5px 10px 2px;width:170px;'.($isManyToOne ? 'border-top:0;' : '')),
									// If a many-to-one mapping, then display "OR" before the variable/label
									RCView::div(array('class'=>'manytoone_or', 'style'=>'margin:0 0 4px 10px;color:#999;'.($isManyToOne ? '' : 'display:none;')),
										"&mdash; ".$lang['global_46']. " &mdash;"
									) .
									// Display variable/label
									RCView::div(array('class'=>'source_var_label'),
										RCView::b($src_field) . " &nbsp;\"" . $src_label . "\""
									) .
									$external_id_field_special_label .
									// Option to perform many-to-one mapping (temporal fields only)
									$temporal_field_dd_many_to_one
								) .
								(!$longitudinal ? '' :
									RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
										($isManyToOne ? '' :
											RCView::div(array('class'=>'td_container_div'),
												$event_dd .
												$mapAnotherEventLink
											)
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
									($isManyToOne ? '' :
										RCView::div(array('class'=>'td_container_div'),
											$rc_field_dropdown .
											$hiddenInputSourceIdMarker .
											$mapAnotherFieldLink .
											$external_id_field_special_label_repeating_instance
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding:5px 10px;'.($isManyToOne ? 'border-top:0;' : '')),
									($isManyToOne ? '' :
										RCView::div(array('class'=>'td_container_div'),
											$temporal_dd
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'class'=>"data $highlight_class", 'style'=>'border-bottom:0;padding-top:5px;text-align:center;width:55px;'),
									$copySrcRow . $removeMappingIcon
								)
							);
					// Set flag for all non-initial events in the subarray
					$firstEventOfItem = false;
				}
			}
			// Set for next loop
			$prev_src_cat = $src_cat;
			$true_prev_src_field = $src_field;
		}

		## PREVIEW FIELDS
		// Get source "preview" fields as array
		$preview_fields = $this->getPreviewFields();
		// Html to delete the associated Preview field drop-down
		$deletePreviewFieldLink = 	RCView::a(array('href'=>'javascript:;', 'onclick'=>"deletePreviewField(this);"),
										RCView::img(array('src'=>'cross_small2.png', 'title'=>$lang['scheduling_57']))
									);
		// Loop through preview fields and build html for drop-downs/divs
		if (empty($preview_fields)) {
			// No preview fields are designated, so render blank drop-down
			$preview_fields_html = 	RCView::div(array('class'=>'rtws_preview_field nowrap mb-1'),
										RCView::select(array('style'=>'max-width:95%;margin-right:3px;', 'name'=>'preview_field[]'), $src_preview_fields_all, '', 60) .
										$deletePreviewFieldLink
									);
		} else {
			// Render all preview field drop-downs
			$preview_fields_html = "";
			foreach ($preview_fields as $this_preview_field) {
				$preview_fields_html .=	RCView::div(array('class'=>'rtws_preview_field nowrap mb-1'),
											RCView::select(array('style'=>'max-width:95%;margin-right:3px;', 'name'=>'preview_field[]'), $src_preview_fields_all, $this_preview_field, 60) .
											$deletePreviewFieldLink
										);
			}
		}

		// Set pixel width of table/divs
		$tableWidth = ($longitudinal ? 958 : 798);

		$importMappingFieldsButton = RCView::button(array('id'=>'button-import','class'=>'jqbuttonmed', 'style'=>'font-size:13px;'),
				RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
				RCView::span(array('style'=>'vertical-align:middle;'), $lang['global_72'])
			);
		$exportMappingFieldsButton = RCView::button(array('id'=>'button-export','class'=>'jqbuttonmed', 'style'=>'font-size:13px;'),
			RCView::img(array('src'=>'xls.gif', 'style'=>'vertical-align:middle;')) .
			RCView::span(array('style'=>'vertical-align:middle;'), $lang['global_71'])
		);
		$importExportTool = RCView::div(array('id'=>'ddp-import-export-tool', 'style'=>'float: right;display: flex;align-content: center;'),
			$importMappingFieldsButton . RCView::SP . $exportMappingFieldsButton
		);

		// Set table html
		$table =
				// Settings header
				RCView::div(array('style'=>'width:'.$tableWidth.'px;font-weight:bold;color:#fff;border:1px solid #ccc;border-bottom:0;background-color:#555;padding:8px 10px;font-size:13px;'),
					$lang['ws_117']
				) .
				// Settings - Preview fields
				RCView::div(array('class'=>"data", 'style'=>'width:'.$tableWidth.'px;background-image:none;background-color:#eee;padding:10px;'),
					RCView::table(array('cellspacing'=>'0', 'style'=>'width:100%;table-layout:fixed;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'width:450px;padding-right:30px;color:#555;line-height:13px;'),
								RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#800000;line-height:14px;margin-bottom:5px;'),
									$lang['ws_118']
								) .
								RCView::div(array('style'=>'font-size:11px;line-height:11px;'),
									$lang['ws_119']." (e.g. {$external_id_field_attr[$external_id_field]['label']}) ".$lang['ws_120']
								)
							) .
							RCView::td(array('valign'=>'top'),
								RCView::div(array('style'=>'font-weight:bold;margin-bottom:5px;'),
									$lang['ws_121'] . " " . self::getSourceSystemName() . " " .
									RCView::span(array('style'=>'color:#800000'), $lang['ws_122']) .
									$lang['colon']
								) .
								// Preview field(s)
								$preview_fields_html .
								// "Add" new Preview field button
								RCView::div(array('class'=>'rtws_add_preview_field', 'style'=>'margin:4px 0 0 6px;'),
									RCView::img(array('src'=>'plus_small2.png')) .
									RCView::a(array('href'=>'javascript:;', 'style'=>'color:green;font-size:10px;text-decoration:underline;', 'onclick'=>"addPreviewField();"),
										$lang['ws_123']
									)
								)
							)
						)
					)
				) .
				// Settings - Default Day Offset
				RCView::div(array('class'=>"data", 'style'=>'width:'.$tableWidth.'px;background-image:none;background-color:#eee;padding:10px;'),
					RCView::table(array('cellspacing'=>'0', 'style'=>'width:100%;table-layout:fixed;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'width:450px;padding-right:30px;color:#555;line-height:13px;'),
								RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#800000;line-height:14px;margin-bottom:5px;'),
									$lang['ws_124']
								) .
								RCView::div(array('style'=>'font-size:11px;line-height:11px;'),
									$lang['ws_125']
								)
							) .
							RCView::td(array('valign'=>'top', 'style'=>'padding-top:15px;'),
								RCView::span(array('style'=>'font-size:13px;font-weight:bold;margin-right:5px;'),
									$lang['ws_126']
								) .
								// Dropdown of day offset plus/minus
								"<select name='rtws_offset_plusminus' style='font-size:12px;'>
									<option value='+-' ".($realtime_webservice_offset_plusminus == '+-' ? 'selected' : '').">&plusmn;</option>
									<option value='+' ".($realtime_webservice_offset_plusminus == '+' ? 'selected' : '').">+</option>
									<option value='-' ".($realtime_webservice_offset_plusminus == '-' ? 'selected' : '').">-</option>
								</select>" .
								// Text box for day offset value
								RCView::text(array('name'=>'rtws_offset_days', 'value'=>$realtime_webservice_offset_days,
									'onblur'=>"redcap_validate(this,'".self::DAY_OFFSET_MIN."','".self::DAY_OFFSET_MAX."','hard','float')", 'class'=>'',
									'style'=>'width:31px;font-size:12px;padding:0 3px;')) .
								RCView::span(array('style'=>'font-size:13px;'),
									$lang['scheduling_25']
								) .
								RCView::div(array('style'=>'margin:10px 0 0;color:#888;font-size:11px;'),
									$lang['ws_192']." ".self::DAY_OFFSET_MIN." ".$lang['ws_191']." ".self::DAY_OFFSET_MAX." ".$lang['scheduling_25']
								)
							)
						)
					)
				) .
				// Field-mapping header
				
				RCView::div(array('style'=>'margin-top:15px;width:'.$tableWidth.'px;font-weight:bold;color:#fff;border:1px solid #ccc;border-bottom:0;background-color:#555;padding:8px 10px;font-size:13px;'),
					$lang['ws_130'].
					$importExportTool // mapping fields import/export
				) .
				// Field-mapping table
				RCView::table(array('id'=>'src_fld_map_table', 'class'=>'form_border',
								'style'=>'border-bottom:1px solid #ccc;table-layout:fixed;width:'.($tableWidth+22).'px;'), $rows);
		// Add Save/Cancel buttons
		if (!empty($external_fields_all)) {
			$table .= RCView::div(array('style'=>'padding:30px 0;text-align:center;width:800px;'),
						RCView::hidden(array('name'=>'map_fields', 'value'=>'1')) .
						RCView::button(array('class'=>'btn btn-sm btn-rcgreen', 'id'=>'map_fields_btn', 'onclick'=>"
							$(this).button('disable');
							$('#rtws_mapping_cancel').css('visibility','hidden');
							$('#rtws_mapping_table').submit();
							return false;
						"),
							$lang['ws_131']
						) .
						RCView::a(array('id'=>'rtws_mapping_cancel', 'href'=>'javascript:;', 'style'=>'margin-left:10px;text-decoration:underline;', 'onclick'=>"window.location.href=app_path_webroot+'DynamicDataPull/setup.php?pid='+pid;"), $lang['global_53'])
					);
		}
		// Set html to return
		$html = '';
		// Add "add more source fields" button
		if (isset($_POST['select_fields'])) {
			$html .= RCView::div(array('style'=>'width:'.$tableWidth.'px;margin:20px 0 5px;padding-top:10px;border-top:1px solid #ccc;'),
						RCView::div(array('style'=>'font-size:15px;font-weight:bold;padding-bottom:8px;margin-bottom:8px;'),
							$lang['ws_132']
						) .
						$lang['ws_133'] . " " .
						RCView::span(array('style'=>'color:#C00000;'),
							$lang['ws_202']
						)
					);
		} else {
			$html .= RCView::div(array('style'=>'padding:10px 0 5px;'),
						RCView::button(array('class'=>'btn btn-sm btn-rcgreen fs13', 'onclick'=>"confirmLeaveMappingChanges(modifiedMapping);"),
							'<i class="fas fa-plus"></i> '.$lang['ws_134']
						)
					);
		}
		// Add form html
		$html .= RCView::form(array('id'=>'rtws_mapping_table', 'method'=>'post', 'action'=>PAGE_FULL."?pid=".$this->project_id, 'enctype'=>'multipart/form-data',
				'onsubmit'=>"
				if (hasMappedAllSelections()) {
					copyItemsManyToOneChildren();
					return !hasDuplicateFieldMapping();
				} else {
					simpleDialog('".js_escape($lang['ws_05'])."');
					enableMappingSubmitBtn();
					return false;
				}",
				'style'=>'margin:20px 0 0;'), $table);
		// If there is any javascript for adding multi-event many-to-one mappings, then display it here
		if ($nonDisplayedManyToOneMappingsJs != '') {
			$html .= "<script type='text/javascript'>$(function(){ $nonDisplayedManyToOneMappingsJs });</script>";
		}
		// Return html
		return $html;
	}


	/**
	 * CALL "DATA" WEB SERVICE AND DISPLAY IN TABLE FOR ADJUDICATION
	 */
	public function fetchAndOutputData($record=null, $event_id=null, $form_data=array(), $day_offset=0, $day_offset_plusminus='+-',
									   $output_html=true, $record_exists='1', $show_excluded=false, $forceDataFetch=true, $instance=1, $repeat_instrument="")
	{
		global $Proj, $lang, $isAjax;
		// Validate $day_offset. If not valid, set to 0.
		if (!is_numeric($day_offset)) $day_offset = 0;
		if ($day_offset_plusminus != '-' && $day_offset_plusminus != '+') $day_offset_plusminus = '+-';
		// Get the REDCap field name and event_id of the external identifier
		list ($rc_field, $rc_event) = $this->getMappedIdRedcapFieldEvent();
		// Obtain the value of the record identifier (e.g., mrn)
		$rc_data = Records::getData('array', $record, $rc_field, $rc_event);
		// Reset some values if we're not on a repeating instrument
		if (!($instance > 0 && is_numeric($event_id) && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $repeat_instrument)))) {
			$repeat_instrument = "";
			$instance = 0;
		} elseif ($Proj->isRepeatingEvent($event_id)) {
			$repeat_instrument = "";
		}
		// If form values were sent (which might be non-saved values), add them on top of $rc_data
		if (!empty($form_data) && is_numeric($event_id)) {
			// Loop through vars and remove all non-real fields
			foreach ($form_data as $key=>$val)
			{
				// If begin with double underscore (this ignores checkboxes, which we can't use for the RTWS)
				if (substr($key, 0, 2) == '__') { unset($form_data[$key]); continue; }
				// If end with ___radio
				if (substr($key, -8) == '___radio') { unset($form_data[$key]); continue; }
				// If contains a hyphen
				if (strpos($key, '-') !== false) { unset($form_data[$key]); continue; }
				// If is a reserved field name
				if (isset(Project::$reserved_field_names[$key])) { unset($form_data[$key]); continue; }

				// If a date[time] field that's not in YMD format, then convert to YMD
				// ONLY do this if being called via AJAX (i.e. form post from data entry form, where dates may not be in YMD format)
				if ($isAjax) {
					$thisValType = $Proj->metadata[$key]['element_validation_type'];
					if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
						$form_data[$key] = $val = DateTimeRC::datetimeConvert($val, substr($thisValType, -3), 'ymd');
					}
				}

				// If this is the REDCap field mapped to the external id field, then overwrite it's value
				if ($key == $rc_field && $event_id == $rc_event) {
					// Overwrite value
					$rc_data[$record][$rc_event][$rc_field] = $val;
				}
			}
		}
		// Get the value of the external id field (e.g., the MRN value)
		$record_identifier_external = $rc_data[$record][$rc_event][$rc_field];
		// If doesn't have a record identifer external for this record, give error message
		if ($record_identifier_external == '') {
			// Go ahead and add timestamp for updated_at for record so that the cron doesn't keep calling it
			$sql = "update redcap_ddp_records set updated_at = '".NOW."', fetch_status = null
					where project_id = ".$this->project_id." and record = '".db_escape($record)."'";
			db_query($sql);
			// Return error message
			return 	array(0, RCView::div(array('class'=>"red", 'style'=>'margin:20px 0;max-width:100%;padding:10px;'),
								RCView::img(array('src'=>'exclamation.png')) .
								RCView::b($lang['global_01'].$lang['colon'])." ".
								"{$lang['global_49']} <b>$record</b> {$lang['ws_135']} \"$rc_field\"{$lang['period']} {$lang['ws_136']}"
							) .
							// Set hidden div so that jQuery knows to hide the dialog buttons
							RCView::div(array('id'=>'adjud_hide_buttons', 'class'=>'hidden'), '1')
					);
		}

		// Get data via web service and return as array
		list ($response_data_array, $request_field_array) =
			$this->fetchData($record, $event_id, $record_identifier_external, $day_offset, $day_offset_plusminus, $form_data, $forceDataFetch, 
							 $record_exists, true, null, $instance, $repeat_instrument);

		// Return html for adjudication table
		return $this->renderAdjudicationTable($record, $event_id, $day_offset, $day_offset_plusminus, $response_data_array, $request_field_array, 
											  $form_data, $output_html, $record_exists, $show_excluded, $instance, $repeat_instrument);
	}

	/**
	 * add/subtract an offset of days to a date provided as a string
	 * 0.01 ~= 15 minutes
	 * @param string $date
	 * @param string $offset (example -340.01) 
	 * @param boolean $add subtract if false
	 * @return string formatted date
	 */
	private function setOffsetDays($date, $offset, $add=true)
	{
		$time = strtotime($date);
		$date_time = new DateTime();
		$date_time->setTimestamp($time);
		$offset_regexp = "/(?<days>[\d]+)(\.(?<minutes>\d{1,2}))?/";
		preg_match($offset_regexp, $offset, $matches);
		if($matches['days']) {
			$days_string = $matches['days'] ?: '';
			$minutes_string =  $matches['minutes'] ?: '';
			$date_interval = DateInterval::createFromDateString("$days_string days");
			if(!empty($minutes_string)) {
				$minutes_decimal = (float)sprintf("0.%s", str_pad( $minutes_string, 2, "0")); // add padding zeroes
				$minutes = 1440*$minutes_decimal;
				$date_interval->i = $minutes;
			}
			if($add) $date_time->add($date_interval);
			else $date_time->sub($date_interval);
		}
		return $date_time->format('Y-m-d H:i:s');
	}


	/**
	 * CALL "DATA" WEB SERVICE TO OBTAIN DATA FROM RECORD PASSED TO IT
	 * Return data as array with unique field name as array keys and data values as array values.
	 * If not JSON encoded, then return FALSE.
	 */
	public function fetchData($record_identifier_rc, $event_id, $record_identifier_external, $day_offset,
							  $day_offset_plusminus, $form_data=array(), $forceDataFetch=true, $record_exists='1', $returnCachedValues=true,
							  $project_id=null, $instance=0, $repeat_instrument="")
	{
		// Get global var
		global 	$realtime_webservice_url_data, $isAjax, $realtime_webservice_data_fetch_interval, $fhir_data_fetch_interval, $lang,
				$realtime_webservice_convert_timestamp_from_gmt, $fhir_convert_timestamp_from_gmt;			
			
		// Ensure that OpenSSL extension is installed on server
		if (!openssl_loaded()) {
			$response = array('error'=> $lang['global_01'] . $lang['colon'] . " " . $lang['global_140']);
			HttpClient::printJSON($response, 500);
			/* print json_encode_rc(array('item_count'=>-2, 'html'=>RCView::b($lang['global_01'] . $lang['colon']) . " " . $lang['global_140']));
			exit; */
		}

		// Determine project_id (either as PROJECT_ID or as passed parameter)
		if ($project_id == null) {
			if (defined("PROJECT_ID")) {
				$project_id = PROJECT_ID;
			} else {
				throw new Exception('No project_id provided!');
			}
		}
		$Proj = new Project($project_id);
		
		// Set interval based on type
		$data_fetch_interval = ($Proj->project['realtime_webservice_type'] == 'FHIR') ? $fhir_data_fetch_interval : $realtime_webservice_data_fetch_interval;

		// Make sure we have a value for $data_fetch_interval
		if (!(is_numeric($data_fetch_interval) && $data_fetch_interval >= 1)) {
			$data_fetch_interval = 24;
		}

		// Get mappings external=>REDCap
		$mappings = $this->getMappedFields();

		// If we have temporal fields, then retrieve REDCap data for them to send in the request
		$temporal_event_ids = $temporal_fields = array();
		foreach ($mappings as $this_src_field=>$this_evt_array) {
			foreach ($this_evt_array as $this_event_id=>$these_rc_fields) {
				foreach ($these_rc_fields as $this_rc_field=>$rc_field_attr) {
					// Add temporal field/event_id for data pull later
					if ($rc_field_attr['temporal_field'] != '') {
						// Get temporal field names and event_id's for the data pull
						$temporal_event_ids[] = $this_event_id;
						$temporal_fields[] = $rc_field_attr['temporal_field'];
					}
				}
			}
		}
		$temporal_fields = array_unique($temporal_fields);
		$temporal_event_ids = array_unique($temporal_event_ids);

		// Get data for temporal fields
		$temporal_data = array();
		if (!empty($temporal_fields)) {
			// Get data from backend
			$temporal_data = Records::getData($project_id, 'array', $record_identifier_rc, $temporal_fields, $temporal_event_ids);
			// If form values were sent (which might be non-saved values), add them on top of $rc_existing_data
			if (!empty($form_data) && is_numeric($event_id)) {
				// Loop through vars and remove all non-real fields
				foreach ($form_data as $key=>$val) {
					// If a temporal field
					if (in_array($key, $temporal_fields)) {
						// ADD to $temporal_data if we made it this far
						if ($instance < 1) {
							$temporal_data[$record_identifier_rc][$event_id][$key] = $val;
						} else {
							// Add in repeating instrument format
							$temporal_data[$record_identifier_rc]['repeat_instances'][$event_id][$repeat_instrument][$instance][$key] = $val;
						}
					}
				}
			}
		}
		
		// Set flag if we're on a data entry form. If so, then filter data to ONLY fields on this form.
		$onDataEntryForm = (!empty($form_data));

		// Get mr_id for this record (assuming the record exists)
		$mr_id = ($record_exists) ? $this->getMrId($record_identifier_rc) : null;

		## CHECK IF RECORD HAS ANY DATA CACHED (if not, then force a data fetch)
		if (!$forceDataFetch && $record_exists)
		{
			$sql = "select 1 from redcap_ddp_mapping m, redcap_ddp_records_data d, redcap_ddp_records r
					where m.map_id = d.map_id and d.mr_id = r.mr_id and m.project_id = " . $this->project_id . "
					and m.project_id = r.project_id and r.record = '".db_escape($record_identifier_rc)."' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) == 0) {
				// Now check if we've ever pulled data for this record in the past X hours, and if not, then set it to force a data fetch
				$lastFetchTimeText = $this->getLastFetchTime($record_identifier_rc);
				// If data has never been fetched OR it's time for it to be fetched, then set to fetch it
				if ($lastFetchTimeText == '' || ((strtotime(NOW)-strtotime($lastFetchTimeText)) > (3600*$data_fetch_interval))) {
					$forceDataFetch = '1';
				}
			}
		}

		// Now loop through mapped fields again to build temporal CSV string ($field_event_info is same as $field_info but with event_id)
		$field_info = $field_event_info = $map_ids = array();
		$recordIdIsMapped = false;
		foreach ($mappings as $this_src_field=>$this_evt_array) {
			foreach ($this_evt_array as $this_event_id=>$these_rc_fields) {
				$isRepeatingEvent = $Proj->isRepeatingEvent($this_event_id);
				foreach ($these_rc_fields as $this_rc_field=>$rc_field_attr) {
					// Skip the record ID field
					if ($rc_field_attr['is_record_identifier']) {
						// Set flag and begin next loop
						$recordIdIsMapped = true;
						continue;
					}

					// If on data entry form and NOT forcing a fresh data fetch (i.e. will get cached data),
					// then skip any fields not on this form/event
					//if ($onDataEntryForm && !$forceDataFetch && (!isset($form_data[$this_rc_field]) || $event_id != $this_event_id)) continue;
					
					// If this is a temporal field that is on a repeating form, then get ALL instances of it
					if ($rc_field_attr['temporal_field'] != '') {
						$this_temporal_field_form = $Proj->metadata[$rc_field_attr['temporal_field']]['form_name'];
						if ($isRepeatingEvent || $Proj->isRepeatingForm($this_event_id, $this_temporal_field_form)) {
							$temporal_field_data = array();
							$this_repeat_form = $isRepeatingEvent ? "" : $Proj->metadata[$rc_field_attr['temporal_field']]['form_name'];
							foreach ($temporal_data[$record_identifier_rc]['repeat_instances'][$this_event_id][$this_repeat_form] as $this_instance=>$idata) {
								$temporal_field_data[$this_instance][$rc_field_attr['temporal_field']] = $idata[$rc_field_attr['temporal_field']];
							}
						} else {
							$temporal_field_data = array(1=>array($rc_field_attr['temporal_field']=>$temporal_data[$record_identifier_rc][$this_event_id][$rc_field_attr['temporal_field']]));
						}
					}
					
					// Add map_id to array for later when pulling cached data (if applicable)
					$map_ids[] = $rc_field_attr['map_id'];
					
					if ($rc_field_attr['temporal_field'] != '') {
						// Get temporal data
						foreach ($temporal_field_data as $this_instance=>$this_instance_temporal_data) 
						{
							// Determine min/max timestamps using $day_offset
							$this_timestamp = $this_instance_temporal_data[$rc_field_attr['temporal_field']];
							// If there is no timestamp value, then skip this temporal field
							if ($this_timestamp == '') continue;
							// Determine min timestamp
							if ($day_offset_plusminus == '+') {
								$this_timestamp_min = date('Y-m-d H:i:s', strtotime($this_timestamp));
							} else {
								$this_timestamp_min = $this->setOffsetDays($this_timestamp, $day_offset, $add=false);
							}
							// Determine max timestamp
							if ($day_offset_plusminus == '-') {
								$this_timestamp_max = date('Y-m-d H:i:s', strtotime($this_timestamp));
							} else {
								$this_timestamp_max = $this->setOffsetDays($this_timestamp, $day_offset, $add=true);
							}
							// Add to array to send to data web service
							$field_info[] = array('field'=>$this_src_field, 'timestamp_min'=>$this_timestamp_min, 'timestamp_max'=>$this_timestamp_max);
							// Add to array for REDCap to use for adjudication setup
							$field_event_info[] = array('src_field'=>$this_src_field, 'rc_field'=>$this_rc_field, 'event_id'=>$this_event_id,
														'timestamp'=>$this_timestamp,
														'preselect'=>$rc_field_attr['preselect']);
						}
					}
					// Either no data or not a temporal field
					elseif ($rc_field_attr['temporal_field'] == '')
					{
						// Add to array to send to data web service
						$field_info[] = array('field'=>$this_src_field);
						// Add to array for REDCap to use for adjudication setup
						$field_event_info[] = array('src_field'=>$this_src_field, 'rc_field'=>$this_rc_field, 'event_id'=>$this_event_id);
					}
				}
			}
		}

		// Remove any duplicates in $field_info so that we don't send superfluous data to the data web service
		$field_info = array_values(array_map("unserialize", array_unique(array_map("serialize", $field_info))));

		if (empty($field_info)) {
			if ($recordIdIsMapped) {
				// Set record's updated_at timestamp as NOW so that we know we processed this
				$sql = "update redcap_ddp_records set updated_at = '".NOW."', fetch_status = null where project_id = ".$this->project_id."
						and record = '".db_escape($record_identifier_rc)."'";
				db_query($sql);
			}
			## No fields to send to web service, so return 0 items
			if (!$isAjax || PAGE == 'ehr.php') {
				return false;
			} else {
				print json_encode_rc(array('item_count'=>0, 'html'=>''));
				exit;
			}
		}

		// Collect the md_id for each data point in an array to map it to the key in $response_data_array
		// (so we can set each saved value as "adjudicated=1" in the mapping_data table.
		$response_data_array_keymap_mdid = array();

		// Call the data web service if the record doesn't exist OR if we're forcing a data fetch
		if ($forceDataFetch || !$record_exists)
		{
			## CALL DATA WEB SERVICE
			// First, set the record's fetch status as QUEUED in case the user leaves the page before it finishes
			// (so they don't have to wait 24 hours for it to be fetched again).
			$sql = "update redcap_ddp_records set fetch_status = 'QUEUED' where mr_id = $mr_id";
			$q = db_query($sql);
			
			// If using built-in FHIR service, call EHR via SMART on FHIR methods
			if ($this->realtime_webservice_type == 'FHIR') {
				// Call EHR via SMART on FHIR methods and return array of data values returned
				try {
					$this->fhirData = $fhir_data = $this->getFhirData($record_identifier_external, $field_info);
					$response_data_array = $fhir_data->getData();
				} catch (\Exception $e) {
					$code = $e->getCode();
					$message = $e->getMessage();
					print json_encode_rc(array('item_count'=>-2, 'html'=>RCView::b("error " . $code . $lang['colon']) . " " . $message));
					exit;
				}
			}
			// Call the custom data web service URL as POST request
			else {
				// Set params to send in POST request (all JSON-encoded in single parameter 'body')
				$params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL,
								'id'=>$record_identifier_external, 'fields'=>$field_info);
				// Call the URL as POST request
				$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
				// if (USERID == 'taylorr4' || isDev())
					// exit(json_encode_rc(array('item_count'=>-2, 'html'=>"JSON being SENT TO the data web service:<br><br>".json_encode($params).
					// "<br><hr><br>JSON being RETURNED FROM the data web service:<br><br>$response_json")));
				// Decode json into array
				$response_data_array = json_decode($response_json, true);
			}
			// Display an error if the web service can't be reached or if the response is not JSON encoded
			if ((isset($response_json) && !$response_json) || !is_array($response_data_array)) {
				$error_msg = $lang['ws_137']."<br><br>";
				if ($response_json !== false && !is_array($response_data_array)) {
					$error_msg .=  $lang['ws_138']."<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
				} elseif ($response_json === false) {
					$error_msg .= $lang['ws_139']." $realtime_webservice_url_data.";
				}
				if (!$isAjax) {
					return false;
				} else {
					print json_encode_rc(array('item_count'=>-2, 'html'=>$error_msg));
					exit;
				}
			}

			## CACHE THE FETCHED DATA IN THE TABLE (but only if the record already exists - could cause issue with naming)
			if ($record_exists)
			{
				// Set flag if we need to convert timestamps from GMT to local
				$convert_timestamp_from_gmt = (($this->realtime_webservice_type == 'CUSTOM' && $realtime_webservice_convert_timestamp_from_gmt == '1')
											  || ($this->realtime_webservice_type == 'FHIR' && $fhir_convert_timestamp_from_gmt == '1'));
				// Count non-temporal fields that have multiple values (to inform user that these should be checkboxes)
				$nonTemporalValueCount = array();
				// Loop through all fetched data and get map_id's for each data point returned.
				foreach ($response_data_array as $item_key=>$this_item) {
					// Loop through mappings of this data value's field
					foreach ($mappings[$this_item['field']] as $this_event_id=>$event_array) {
						foreach ($event_array as $this_rc_field=>$rc_field_array) {
							// reset current_item to the original value of this_item;
							$current_item = $this_item;
							// Get map_id
							$this_map_id = $rc_field_array['map_id'];
							if ($current_item['timestamp'] != '') {
								// Clean the timestamp in case ends with ".0" or anything else
								$current_item['timestamp'] = substr($current_item['timestamp'], 0, 19);
								// If we're shifting the timestamp from GMT to local/server time, then convert time
								if ($convert_timestamp_from_gmt) 
								{
									$userTimezone = new DateTimeZone(getTimeZone());
									$gmtTimezone = new DateTimeZone('GMT');
									$myDateTime = new DateTime($current_item['timestamp'], $gmtTimezone);
									$offset = $userTimezone->getOffset($myDateTime);
									$myInterval = DateInterval::createFromDateString((string)$offset . 'seconds');
									$myDateTime->add($myInterval);
									$current_item['timestamp'] = $myDateTime->format('Y-m-d H:i:s');
								}
							}
							// Prepare timestamp for the query
							$this_timestamp = ($current_item['timestamp'] == '') ? 'null' : "timestamp('{$current_item['timestamp']}')";
							// If a non-temporal field, put counter in array
							if ($current_item['timestamp'] == '') {
								if (isset($nonTemporalValueCount[$this_rc_field])) {
									$nonTemporalValueCount[$this_rc_field]++;
								} else {
									$nonTemporalValueCount[$this_rc_field] = 0;
								}
							}
							// Make sure value gets trimmed before storing it (just in case of whitespace padding)
							$current_item['value'] = trim($current_item['value']);
							// Set the encrypted value
							$this_encrypted_value = encrypt($current_item['value'], self::DDP_ENCRYPTION_KEY);
							// Check if this timestamp-value already exists. If not, then add.
							$sql = "select md_id, source_value2 from redcap_ddp_records_data
									where map_id = $this_map_id and mr_id = $mr_id
									and source_timestamp " . ($current_item['timestamp'] == '' 
										? 	("is null" . ($Proj->isCheckbox($this_rc_field) 
														? " and source_value2 = '".db_escape($this_encrypted_value)."'" 
														: "")
											)
										: 	"= $this_timestamp and source_value2 = '".db_escape($this_encrypted_value)."'")."
									limit 1";
							$q = db_query($sql);
							$alreadyCached = (db_num_rows($q) > 0);
							if ($alreadyCached) {
								// Get existing md_id and source value
								$response_data_array[$item_key]['md_id'] = db_result($q, 0, 'md_id');
								$cachedSourceValue = db_result($q, 0, 'source_value2');
								// Update value in values table if the non-temporal value has somehow changed since the time it was cached
								if ($cachedSourceValue != $this_encrypted_value) {
									$sql = "update redcap_ddp_records_data set source_value2 = '".db_escape($this_encrypted_value)."'
											where md_id = " . $response_data_array[$item_key]['md_id'];
									$q = db_query($sql);
								}
							} else {
								// Add value to values table
								$sql = "insert into redcap_ddp_records_data (map_id, mr_id, source_timestamp, source_value2)
										values ($this_map_id, $mr_id, $this_timestamp, '".db_escape($this_encrypted_value)."')";
								$q = db_query($sql);
								// Add md_id to keymap array
								$response_data_array[$item_key]['md_id'] = db_insert_id();
							}
							// add entry to FHIR stats collector
						}
					}
				}
				
				$this->nonTemporalMultipleValueFields = array();
				foreach ($nonTemporalValueCount as $this_field=>$this_count) {
					if ($this_count > 0 && !$Proj->isCheckbox($this_field)) {
						$this->nonTemporalMultipleValueFields[] = $this_field;
					}
				}
				unset($nonTemporalValueCount);

				## Check to see how many temporal fields' datetime reference fields have values that will occur in the future
				// Set initial count
				$future_dates = 0;
				// Fetch data only for temporal fields
				$data_temporal_fields = Records::getData($project_id, 'array', $record_identifier_rc, $temporal_fields);
				// Loop through and count if value exists in the future
				foreach ($data_temporal_fields[$record_identifier_rc] as $this_event_id=>$these_fields) {
					if ($this_event_id == 'repeat_instances') {
						foreach ($these_fields as $attr) {
							foreach ($attr as $bttr) {
								foreach ($bttr as $cttr) {									
									foreach ($cttr as $this_field=>$this_value) {
										// Is value a date AND occurs today or after today's date
										if ($this_value != '' && $this_value >= TODAY) $future_dates++;
									}
								}
							}
						}
					} else {
						foreach ($these_fields as $this_field=>$this_value) {
							// Is value a date AND occurs today or after today's date
							if ($this_value != '' && $this_value >= TODAY) $future_dates++;
						}
					}
				}

				// Now add "last fetch" timestamp for the record as well as future_date_count
				$sql = "update redcap_ddp_records set updated_at = '".NOW."', fetch_status = null,
						future_date_count = $future_dates where mr_id = $mr_id";
				$q = db_query($sql);
			}

			// print "<b>POST params sent to Data Web Service:</b>";
			// print_array($params);
			// print "<b>POST['fields'] after json_decode():</b>";
			// print_array($field_info);
			// print "<b>RESPONSE data after json_decode():</b>";
			// print_array($response_data_array);
		}

		## IF WE'RE NOT RETURNING ANY CACHED VALUES (I.E. CRON - JUST CACHING VALUES), THEN STOP HERE
		if (!$returnCachedValues) return;

		## GET THE CACHED DATA IN TABLE (even if we just cached it - just in case other values were already cached so we don't miss them)
		$response_data_array = array();
		$sql = "select m.field_name, d.md_id, m.external_source_field_name, d.source_timestamp, m.event_id, d.source_value, d.source_value2
				from redcap_ddp_mapping m, redcap_ddp_records_data d, redcap_ddp_records r
				where m.map_id = d.map_id and d.mr_id = r.mr_id and m.project_id = " . $this->project_id . "
				and m.project_id = r.project_id and r.record = '".db_escape($record_identifier_rc)."'
				and m.map_id in (".prep_implode($map_ids).")";
		$q = db_query($sql);
		//print $sql;
		while ($row = db_fetch_assoc($q)) 
		{
			$use_mcrypt = ($row['source_value2'] == '');
			$source_value = $use_mcrypt ? $row['source_value'] : $row['source_value2'];
			$response_data_array[] = array('field'=>$row['external_source_field_name'], 'timestamp'=>$row['source_timestamp'],
										   'value'=>decrypt($source_value, self::DDP_ENCRYPTION_KEY, $use_mcrypt),
										   'md_id'=>$row['md_id'], 'event_id'=>$row['event_id'], 'rcfield'=>$row['field_name']);
		}

		
		// ob_start();
		// print_array($response_data_array);
		// print json_encode_rc(array('item_count'=>-2, 'html'=>ob_get_clean()));
		// exit;
		

		// Return array of response data AND $field_info array (or false if not JSON encoded)
		return (is_array($response_data_array) ? array($response_data_array, $field_event_info) : false);
	}
	

	// Convert CSV string to associative array with first row of CSV as sub-array keys.
	// Set array keys as the unique FHIR variable names.
	private function fhirCsvMetadataToArray($data)
	{
		// Trim the data, just in case
		$data = trim($data);
		// Add CSV string to memory file so we can parse it into an array
		$h = fopen('php://memory', "x+");
		fwrite($h, $data);
		fseek($h, 0);
		// Now read the CSV file into an array
		$data = array();
		$csv_headers = array();
		while (($row = fgetcsv($h, 0, ",")) !== false) {
			if (empty($csv_headers)) {
				$csv_headers = $row;
			} else {
				// If row is completely blank, then skip it
				if (strlen(trim(implode("", $row))) > 0) {
					$data[$row[0]] = array_combine($csv_headers, $row);
				}
			}
		}
		fclose($h);
		unset($csv_headers, $row);
		return $data;
	}
	
	// Call EHR via SMART on FHIR methods and return array of data values returned
	public static function getFhirMetadata()
	{
		global $fhir_include_email_address, $fhir_include_email_address_project;
		// Parse the metadata file
		$metadata_csv_filepath = APP_PATH_DOCROOT . "Resources/misc/redcap_fhir_metadata.csv";
		// Convert to array format
		$metadata_array = self::fhirCsvMetadataToArray(file_get_contents($metadata_csv_filepath));
		// Apply list of curated LOINC labs to remove non-essential labs
		$metadata_array = self::applyCuratedLoincLabs($metadata_array);
		// If system-level setting is set to exclude email address field, then remove it from metadata list
        if ($fhir_include_email_address != '1' || $fhir_include_email_address_project != '1') {
            unset($metadata_array['email']);
        }
		// Return array
		return $metadata_array;
	}

	/**
	 * Call EHR via SMART on FHIR methods and return array of data values returned
	 *
	 * @param string $record_identifier_external
	 * @param array $field_info
	 * 
	 * @return FhirData results with data and errors (if any)
	 */
	public function getFhirData($record_identifier_external, $field_info=array(), $isDataMart=false)
	{
		global $lang, $userid, $project_id;
		// Obtain the FHIR patient id
		$fhirEhr = new FhirEhr();
		
		$user_id = User::getUIIDByUsername($userid);
		//init the FhirData  object
		$fhirData = new FhirData();

		// retrieve FHIR ID. can throw error
		try {
			$patient_id = $fhirEhr->getPatientIdFromMrn($record_identifier_external);
		} catch (\Exception $e) {
			$fhirData->addError('Error getting the FHIR ID', $e);
			// If we can't match the MRN to a patient ID for some reason, then stop here with empty data set
			return $fhirData;
		}
		// Create array of unique field names for which we're requesting data
		$fields = array_unique(array_column($field_info, 'field'));

		$tokenManager = new FhirTokenManager($user_id, $patient_id);
		$fhir_urls =  $this->getFhirResourceUrls($patient_id, $field_info);
		while ($fhir_url_data = current($fhir_urls)) {
			try {
				if(
					!isset($fhir_url_data['url']) ||
					!isset($fhir_url_data['resource_type'])
				) throw new Exception("Invalid URL provided", 400);
				
				$resource_type = $fhir_url_data['resource_type'];
				$url = $fhir_url_data['url'];
				$access_token = $tokenManager->getAccessToken();
				// if(rand(1,10)>5) $access_token = "WRONG";
				\Logging::logEvent( "", "FHIR", "MANAGE", "", $url, "Fetching data from FHIR endpoint" );
				$response = FhirEndpoint::getFhirData($url, $access_token);
				$fhir_data_raw = json_decode($response);
				if($next_url = $this->checkPaginatedResults($fhir_data_raw))
				{
					$fhir_urls[] = array(
						'resource_type'=>$resource_type,
						'url'=>$next_url,
					);
				}
				FhirLogsMapper::log(array(
					'user_id' => $user_id,
					'fhir_id' => $patient_id,
					'mrn' => $record_identifier_external,
					'project_id' => $project_id,
					'resource_type' => $resource_type,
					'status' => FhirLogsMapper::STATUS_OK,
					'created_at' => NOW,
				));
				$data = $fhirEhr->parseFhirData($fhir_data_raw, $fields, $resource_type, $returnAllFieldsWithLabel=false, $isDataMart);
				
				// Sort all temporal data chronologically
				$timestamps = array_column($data, 'timestamp');
				array_multisort($timestamps, SORT_REGULAR, $data);

				$fhirData->addData($data);
			} catch (\Exception $e) {
				// the most frequent errrors are 401 (access token not valid), 403 (user is forbidden to access this endpoint) and timeout errors
				$message = $lang['global_01'].$lang['colon']." ".$lang['data_entry_400']." <b>$resource_type</b> (e.g., <i>$resource_type.Read</i> ".$lang['global_47']." <i>$resource_type.Search</i>) ".$lang['data_entry_401'];
				$fhirData->addError($message, $e);

				$code = $e->getCode(); // get exception code
				if(strtolower($resource_type)==='patient' && $code===404)
				{
					// remove patient FHIR ID from DB if we get a "not found" error 
					$tokenManager->removeCachedPatient($patient_id);
				}
				
				FhirLogsMapper::log(array(
					'user_id' => $user_id,
					'fhir_id' => $patient_id,
					'mrn' => $record_identifier_external,
					'project_id' => $project_id,
					'resource_type' => $resource_type,
					'status' => $e->getCode(),
					'created_at' => NOW,
				));
			}finally {
				// always go to next element in the array
				next($fhir_urls);
			}
		}
		return $fhirData;
	}

	/**
	 * check if the FHIR results (typicall a bundle)
	 * contains a pagination link
	 * @param object $fhir_data_raw FHIR data
	 * @return string|null
	 */
	private function checkPaginatedResults($fhir_data_raw)
	{
		if($links = $fhir_data_raw->link) {
			foreach($links as $link) {
				if($link->relation==='next') {
					return $link->url;
				}
			}
		}
	}

	/**
	 * Get urls for fetching FHIR data.
	 * If fields are more than $maximum_fields
	 * then chunk them and create multiple URLs for teh same resource
	 * Chunking is neccessary to avoid errors for too long URIs
	 *
	 * @param string $patient_id
	 * @param array $field_info array with field settings: field, timestamp_min, timestamp_max
	 * @param integer $maximum_fields maximum number of fields allowed per request
	 * @return array <{resource_type: string, url: string}>
	 */
	public function getFhirResourceUrls($patient_id, $field_info, $maximum_fields=50)
	{
		$actions = array();
		$grouped_parameters = $this->groupFieldsInFhirResources($field_info); // group the REDCap fields by resource type
		
		// helper function to get a structured object
		$getResourceUrl = function($resource_type, $url)
		{
			return array(
				'resource_type' => $resource_type,
				'url' => $url,
			);
		};

		$base_URL = FhirEhr::getFhirEndpointBaseUrl();
		foreach ($grouped_parameters as $resource_type => $parameters)
		{
			$endpoint = FhirEndpoint::getInstance($resource_type, $base_URL);
			$interaction = (strtolower($resource_type) === 'patient') ? 'read' : 'search'; // read for Patient, search for all the others
			if($interaction=='read')
			{
				$url = $endpoint->getURL('read', $patient_id);
				$actions[] = $getResourceUrl($resource_type, $url);
				continue;
			}
			$fields = $parameters['fields'] ?: array();
			// check if we have more than $maximum_fields in each group
			if(count($fields) <= $maximum_fields=50)
			{
				$query_string = $endpoint->getQueryStringFromRedcapParameters($patient_id, $parameters);
				$url = $endpoint->getURL('search', $query_string );
				$actions[] = $getResourceUrl($resource_type, $url);
			}else
			{
				// too many fields, must be chuncked
				$chunks = array_chunk($fields, $maximum_fields);
				foreach($chunks as $chunk)
				{
					$chuncked_parameters = $parameters; // copy original parameters
					$chuncked_parameters['fields'] = $chunk; // get a chunk of fields
					$query_string = $endpoint->getQueryStringFromRedcapParameters($patient_id, $chuncked_parameters);
					$url = $endpoint->getURL('search', $query_string );
					$actions[] = $getResourceUrl($resource_type, $url);
				}
			}
		}
		return $actions;
	}
	
	/**
	 * group the FHIR fields in groups
	 * the groups will be used to determine what FHIR calls we need to make
	 * and what parameters to pass to the EHR via SMART on FHIR
	 *
	 * @param array $field_info
	 * @return array FHIR resources
	 */
	private function groupFieldsInFhirResources($field_info)
	{
		$resources = array();
		// Get metadata to determine how to map the fields to specific FHIR end-points
		$metadata_array = self::getFhirMetadata();
		// Loop through fields to map them to a FHIR request type
		foreach ($field_info as $key=>$attr) 
		{
			// If field  not valid, then ignore
			if (!isset($attr['field']) || !isset($metadata_array[$attr['field']])) continue;
			// Get the field's category in the metadata
			$category = $metadata_array[$attr['field']]['category'];
			// Get the FHIR endpoint
			if (!isset($this->fhirMetadataCategoryMapping[$category])) continue;
			$fhirEndpoint = $this->fhirMetadataCategoryMapping[$category];			
			// Get any timestamps
			if (isset($attr['timestamp_min'])) {
				list ($minDate, $nothing) = explode(" ", $attr['timestamp_min'], 2);
				list ($maxDate, $nothing) = explode(" ", $attr['timestamp_max'], 2);
			} else {
				$minDate = $maxDate = "";
			}
			// Add to array
			if (!isset($resources[$fhirEndpoint])) {
				$resources[$fhirEndpoint] = array('minDate'=>$minDate, 'maxDate'=>$maxDate, 'fields'=>array($attr['field']));
			} else {
				// Add min/max if lower or higher, respectively
				if ($minDate != "") {
					if ($resources[$fhirEndpoint]['minDate'] == "") {
						$resources[$fhirEndpoint]['minDate'] = $minDate;
					} else {
						$resources[$fhirEndpoint]['minDate'] = min(array($minDate, $resources[$fhirEndpoint]['minDate']));
					}
				}
				if ($maxDate != "") {
					$resources[$fhirEndpoint]['maxDate'] = max(array($maxDate, $resources[$fhirEndpoint]['maxDate']));
				}
			}
			// Add field
			if (!in_array($attr['field'], $resources[$fhirEndpoint]['fields'])) {
				$resources[$fhirEndpoint]['fields'][] = $attr['field'];
			}
		}
		return $resources;
	}

	/**
	 * RENDER THE DATA ADJUDICATION TABLE AFTER RECEIVING DATA FORM WEB SERVICE
	 */
	public function renderAdjudicationTable($record, $event_id, $day_offset, $day_offset_plusminus, $data_array_src,
											$data_array_rc, $form_data, $output_html, $record_exists, $show_excluded=false, 
											$instance=0, $repeat_instrument="")
	{
		global $Proj, $isAjax, $lang, $longitudinal;

		// If no data or error with web service, then display error message
		if ($data_array_src === false || empty($data_array_src)) {
			return 	array(0, RCView::div(array('class'=>"darkgreen", 'style'=>'padding:10px;max-width:100%;'),
								RCView::img(array('src'=>'accept.png')) .
								$lang['ws_140']
							)
					);
		}

		// Get mappings external=>REDCap
		$mappings = $this->getMappedFields();

		## GET EXISTING REDCAP DATA
		// Loop through all mapped fields to get fields/event_ids needed for REDCap data pull
		$rc_mapped_fields = $rc_mapped_events = $map_ids = $map_id_list = $temporal_fields = array();
		foreach ($mappings as $src_field=>$event_attr) {
			foreach ($event_attr as $this_event_id=>$field_attr) {
				// Add event_id
				$rc_mapped_events[] = $this_event_id;
				// Loop through fields
				foreach ($field_attr as $rc_field=>$attr) {
					// Add field
					$rc_mapped_fields[] = $rc_field;
					// Segregate map_id's into separate array with event_id-field as keys
					$map_ids[$src_field][$this_event_id][$rc_field] = $attr['map_id'];
					// Put all map_ids in an array
					$map_id_list[] = $attr['map_id'];
					// Add temporary field to array
					if ($attr['temporal_field'] != '') {
						$temporal_fields[$rc_field] = $attr['temporal_field'];
					}
				}
			}
		}
		$rc_mapped_events = array_unique($rc_mapped_events);
		$rc_mapped_fields = array_unique($rc_mapped_fields);

		// Get array of md_ids with values already excluded for the given record
		// $excluded_values_by_md_id = $this->getExcludedValues($record, $map_id_list);
		$excluded_values_by_md_id = array(); ## DO NOT USE THIS FEATURE [YET]

		// Get array of md_ids as keys for items/values not yet adjudicated for the given record
		$non_adjudicated_values_by_md_id = $this->getNonAdjudicatedValues($record, $map_id_list);
		$adjudicated_values_by_md_id = $this->getAdjudicatedValues($record, $map_id_list);
		
		// Loop through mapped RC fields and create array of JUST the multiple choice fields with their enums as a sub-array
		// (to use for displaying the option choice in adjudication table).
		$rc_mapped_fields_choices = array();
		foreach ($rc_mapped_fields as $this_field) {
			if ($Proj->isMultipleChoice($this_field)) {
				$rc_mapped_fields_choices[$this_field] = parseEnum($Proj->metadata[$this_field]['element_enum']);
			}
		}
		
		// If project is using repeating forms, then include the form status field of each mapped field so that
		// every instance of that form is returned from getData()
		if ($Proj->hasRepeatingFormsEvents()) {
			$rc_mapped_fields_form_status = array();
			foreach ($rc_mapped_fields as $this_field) {
				$rc_mapped_fields_form_status[] = $Proj->metadata[$this_field]['form_name']."_complete";
			}
			$rc_mapped_fields = array_merge($rc_mapped_fields, array_unique($rc_mapped_fields_form_status));
		}

		## GET EXISTING DATA AND (IF ON A FORM) DATA CURRENTLY ON FORM
		// Pull saved REDCap data for this record for the mapped fields
		$rc_existing_data = Records::getData('array', $record, array_merge($rc_mapped_fields, $temporal_fields), $rc_mapped_events);
		// If form values were sent (which might be non-saved values), add them on top of $rc_existing_data
		if (!empty($form_data) && is_numeric($event_id)) {
			// Loop through vars and remove all non-real fields
			foreach ($form_data as $key=>$val) {
				// ADD to $rc_existing_data if we made it this far
				if ($instance < 1) {
					$rc_existing_data[$record][$event_id][$key] = $val;
				} else {
					// Add in repeating instrument format
					$rc_existing_data[$record]['repeat_instances'][$event_id][$repeat_instrument][$instance][$key] = $val;
				}
			}
		}

		// LOCKING: Collect form_name/event_ids that are locked for this record (1st level key is event_id, 2nd level key is form_name)
		$lockedFormsEvents = array();
		$sql = "select event_id, form_name, instance from redcap_locking_data 
				where project_id = " . $this->project_id . " and record = '" . db_escape($record) . "'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set instance to 0 for non-repeating forms
			if (!$Proj->isRepeatingForm($row['event_id'], $row['form_name']) && !$Proj->isRepeatingEvent($row['event_id'])) {
				$row['instance'] = 0;
			}
			$lockedFormsEvents[$row['event_id']][$row['form_name']][$row['instance']] = true;
		}

		// print_array($mappings);
		// print_array($data_array_rc);

		// Convert the source data into other array with src_field as key (for better searching)
		$source_data = $md_ids_event_ids = $md_ids_fields = array();
		foreach ($data_array_src as $attr) {
			// Add SRC value and timestamp (if a temporal field)
			if (isset($attr['timestamp'])) {
				// Clean the timestamp in case ends with ".0" or anything else
				$attr['timestamp'] = substr($attr['timestamp'], 0, 19);
				// Add to array
				$source_data[$attr['field']][] = array('src_value'=>$attr['value'], 'src_timestamp'=>$attr['timestamp'], 'md_id'=>$attr['md_id']);
			} else {
				// Add to array
				$source_data[$attr['field']][] = array('src_value'=>$attr['value'], 'md_id'=>$attr['md_id']);
			}
			// Map event_id to md_id for filtering later when using multiple events of data, in which some of the same data may be cached in separate events (i.e. duplicates)
			$md_ids_event_ids[$attr['md_id']] = $attr['event_id'];
			// Map field to md_id
			$md_ids_fields[$attr['md_id']] = $attr['rcfield'];
		}

		## Merge all RC field info and data with all Source field info and data
		// First loop through REDCap data and add to $rc_source_data
		$rc_source_data = array();
		foreach ($data_array_rc as $attr) {
			// If this field is on a repeating form...
			if ($Proj->isRepeatingEvent($attr['event_id']) || $Proj->isRepeatingForm($attr['event_id'], $Proj->metadata[$attr['rc_field']]['form_name'])) {
				$data_array_rc_instances = $rc_existing_data[$record]['repeat_instances'][$attr['event_id']];
			} else {
				$data_array_rc_instances = array(""=>array(0=>$rc_existing_data[$record][$attr['event_id']]));
			}
			foreach ($data_array_rc_instances as $this_repeat_instrument=>$iattr4) {
				foreach ($iattr4 as $this_instance=>$this_instance_data) {
					// Add RC data value, if exists
					$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_value'] = $this_instance_data[$attr['rc_field']];
					// Add RC timestamp (if a temporal field)
					if (($this_instance > 0 ? isset($this_instance_data[$temporal_fields[$attr['rc_field']]]) : isset($attr['timestamp']))) {
						// With multiple instances, we'll have to retrieve the timestamp from the instance data
						$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_timestamp'] = ($this_instance > 0 ? $this_instance_data[$temporal_fields[$attr['rc_field']]] : $attr['timestamp']);
						$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_preselect'] = $attr['preselect'];
					}
					// Add src_field placeholder
					$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['src_fields'][$attr['src_field']] = array();
				}
			}
			unset($data_array_rc_instances);
		}
		// Store the md_id's not in range that won't get displayed
		$md_ids_out_of_range = array();
		
		// Now loop through the $rc_source_data and pile the source data on top (and also checking if timestamp is in range for that event)
		foreach ($rc_source_data as $this_event_id=>$evt_attr3) {
			foreach ($evt_attr3 as $this_repeat_instrument=>$iattr3) {
				foreach ($iattr3 as $this_instance=>$bttr3) {
					foreach ($bttr3 as $rc_field=>$fld_attr5) {
						foreach (array_keys($fld_attr5['src_fields']) as $src_field) {
							// If src_field exists in $source_data, then add it to $rc_source_data
							if (isset($source_data[$src_field])) {
								// If temporal, then add as array
								if ($Proj->isCheckbox($rc_field) || isset($source_data[$src_field][0]['src_timestamp'])) {
									// Loop through each timestamp and determine if is in datetime range given the day offset
									foreach ($source_data[$src_field] as $this_src_val_time) {
										// If the md_id of this value does not belong to this_event_id, then skip
										if ($md_ids_event_ids[$this_src_val_time['md_id']] != $this_event_id) continue;
										// If the md_id of this value is not mapped to this field, then skip
										if ($md_ids_fields[$this_src_val_time['md_id']] != $rc_field) continue;
										// Is date in range?
										//print "$this_event_id,$this_instance, $src_field; times: {$this_src_val_time['src_timestamp']}, {$fld_attr5['rc_timestamp']}\n";
										if ($Proj->isCheckbox($rc_field) || self::dateInRange($this_src_val_time['src_timestamp'], $fld_attr5['rc_timestamp'], $day_offset, $day_offset_plusminus)) {
											// Add temporal value
											$rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field][] = $this_src_val_time;
										} else {
											// Store the md_id's not in range that won't get displayed
											$md_ids_out_of_range[] = $this_src_val_time['md_id'];
										}
									}
								}
								// If not temporal, add just as single value
								else {
									$rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field] = $source_data[$src_field][0];
								}
							}
							// If src_field attribute array is empty, then remove it (didn't have data that was in the date range)
							if (is_array($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field])
								&& empty($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field]))
							{
								unset($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field]);
							}
						}
					}
				}
				// Sort the subarrays by instance #
				$iattr = $rc_source_data[$this_event_id][$this_repeat_instrument];
				ksort($iattr);
				$rc_source_data[$this_event_id][$this_repeat_instrument] = $iattr;
			}
			// Sort the subarrays by repeat instrument name
			$evt_attr = $rc_source_data[$this_event_id];
			ksort($evt_attr);
			$rc_source_data[$this_event_id] = $evt_attr;
		}
		unset($md_ids_event_ids);

		// print_array($form_data);
		// print_array($data_array_rc);
		// print_r($source_data);
		// print_r($rc_source_data);

		## FIELD VALIDATION
		// Obtain an array of all Validation Types (from db table)
		$valTypes = getValTypes();
		// Put all fields with field validation into array with field as key and regex_php as value to validate fields later
		$valFields = array();
		foreach ($rc_mapped_fields as $this_field)
		{
			// Get field's validation type
			$thisValType = $Proj->metadata[$this_field]['element_validation_type'];
			// Convert legacy validtion types
			if ($thisValType == "") {
				continue;
			} else {
				$thisValType = convertLegacyValidationType(convertDateValidtionToYMD($thisValType));
			}
			// Add to array
			$valFields[$this_field] = $valTypes[$thisValType]['regex_php'];
		}

		// Set HTML label for INVALID SOURCE VALUE
		$invalidSrcValueLabel = RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:red;white-space:normal;line-height:8px;'),
									RCView::img(array('src'=>'exclamation.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['ws_08']
										)
								);
		// Set HTML label for if the REDCap VALUE = SOURCE VALUE when only ONE source value exists
		$sameSingleSrcValueLabel = RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:green;white-space:normal;line-height:8px;'),
									RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle;')) .
									RCView::span(array('style'=>'vertical-align:middle;'),
										$lang['ws_14']
									)
								);
		// Set HTML label for if the REDCap field is on a LOCKED form/event
		$lockedSrcValueLabel = 	RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;'),
									RCView::img(array('src'=>'lock.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['esignature_29']
										)
								);
		// Set HTML label for if the REDCap field value is out of range
		$outOfRangeSrcValueLabel = 	RCView::div(array('style'=>'margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;'),
									RCView::img(array('src'=>'exclamation_orange.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['dataqueries_58']
										)
								);

		// Get external system ID field
		$external_id_field = $this->getMappedIdFieldExternal();
		$ext_id_fields_keys = array_keys($mappings[$external_id_field]);
		$external_id_rc_event = array_pop($ext_id_fields_keys);
		$ext_id_event_keys = array_keys($mappings[$external_id_field][$external_id_rc_event]);
		$external_id_rc_field = array_pop($ext_id_event_keys);
		$external_id_value = $rc_existing_data[$record][$external_id_rc_event][$external_id_rc_field];

		// Create array of REDCap fields/events where the RC value matches a SRC value that was returned
		// Array key = "event_id-RC field name"
		$rcFieldsSameValue = $rcAllFieldsEvents = array();

		// Keep count of total exclusions
		$total_exclusions = 0;

		// Keep count of items where all values are excluded
		$total_items_all_excluded = 0;

		## SORT ALL THE DATA ABOUT TO BE DISPLAYED IN THE TABLE
		// If we have more than one event of data, then sort the events in the correct order
		if (count($rc_source_data) > 1) {
			// Loop through all existing PROJECT events in their proper order
			$rc_source_data_reordered = array();
			foreach (array_keys($Proj->eventInfo) as $this_event_id) {
				// Does this event_id exist for this record?
				if (isset($rc_source_data[$this_event_id])) {
					// Add this event's data to reordered data array
					$rc_source_data_reordered[$this_event_id] = $rc_source_data[$this_event_id];
				}
			}
			// Set back as new
			$rc_source_data = $rc_source_data_reordered;
			unset($rc_source_data_reordered);
		}
		
		// Now sort all fields according to field order within each event
		foreach ($rc_source_data as $this_event_id=>&$evt_attr) {
			foreach ($evt_attr as $this_repeat_instrument=>&$iattr) {
				foreach ($iattr as $this_instance=>&$rc_array) {
					// Gather all field_order values for fields in this event into an array
					$field_orders = array();
					foreach (array_keys($rc_array) as $this_rc_field) {
						$field_orders[] = $Proj->metadata[$this_rc_field]['field_order'];
					}
					// Now sort $rc_array according to field_order value
					array_multisort($field_orders, SORT_REGULAR, $rc_array);
				}
			}
		}
		unset($evt_attr, $iattr, $rc_array);

		// ob_start();
		// print_array($rc_source_data);
		// print json_encode_rc(array('item_count'=>-2, 'html'=>ob_get_clean()));
		// exit;

		// Set default for flag if any temporal fields will be displayed in the popup
		$rtws_temporal_fields_displayed = 0;
		$totalUnadjudicatedValues = 0;
		$rcFieldsAdjudicatedValues = array();

		$rows = '';
		
		//print_r($rc_source_data);

		## LOOP THROUGH ALL RC FIELDS TO CREATE EACH AS A TABLE ROW
		$last_event_id = 0;
		foreach ($rc_source_data as $this_event_id=>$evt_attr2)
		{
			// Get event name
			$this_event_name = $Proj->eventInfo[$this_event_id]['name_ext'];
						
			// Is repeating event?					
			$isRepeatingEvent = $Proj->isRepeatingEvent($this_event_id);

			// SECTION HEADER: If longitudinal, display the event name as a table header
			if ($longitudinal && $output_html) {
				$rows .=	RCView::tr(array('class'=>"adjud_evt_hdr " . ($event_id == $this_event_id ? "" : "rtws-otherform"), 'evtid'=>$this_event_id),
								RCView::td(array('style'=>'border-top:2px solid #999;padding:5px;color:#fff;background-color:#555;font-weight:bold;', 'colspan'=>7),
									$this_event_name
								)
							);
			}
			
			// Loop through repeat instruments
			foreach ($evt_attr2 as $this_repeat_instrument=>$rattr2)
			{
				// Loop through RC fields in this event
				foreach ($rattr2 as $this_instance=>$iattr2)
				{
					// SECTION HEADER: If repeating event, display the event name as a table header
					if ($longitudinal && $this_instance > 0 && $this_repeat_instrument == '' && $this_event_id != $last_event_id) {
						$this_instance_num = ($this_instance > 0) ? " (#" . $this_instance . ")" : "";
						$rows .=	RCView::tr(array('class'=>"adjud_evt_hdr adjud_rptinst_hdr " . ($event_id == $this_event_id ? "" : "rtws-otherform") . " " . ($instance == $this_instance ? "" : "rtws-otherform"), 
										'evtid'=>$this_event_id, 'rptinst'=>$this_repeat_instrument."-".$this_instance),
										RCView::td(array('style'=>'border-top:2px solid #999;padding:5px;color:#fff;background-color:#555;font-weight:bold;', 'colspan'=>7),
											$this_event_name . $this_instance_num
										)
									);
					}
					
					// SECTION HEADER: If a repeating form, display the instance number as a table header
					if ($this_instance > 0 && $this_repeat_instrument != '' && $output_html) {
						$rows .=	RCView::tr(array('class'=>"adjud_rptinst_hdr " . ($instance == $this_instance ? "" : "rtws-otherform"), 'rptinst'=>$this_repeat_instrument."-".$this_instance),
										RCView::td(array('style'=>'border-top:2px solid #999;padding:5px;color:#fff;background-color:#555;font-weight:bold;', 'colspan'=>($longitudinal ? 7 : 6)),
											RCView::escape($Proj->forms[$this_repeat_instrument]['menu']) . " (#" . $this_instance . ")"
										)
									);
					}
					
					// Loop through RC fields in this event
					$lastFormName = '';
					foreach ($iattr2 as $rc_field=>$fld_attr)
					{
						// Get the RC field's validation type
						$thisValType = $Proj->metadata[$rc_field]['element_validation_type'];

						// Get the RC field's form_name
						$thisFormName = $Proj->metadata[$rc_field]['form_name'];
						
						// Is the field a checkbox field type?
						$isCheckbox = $Proj->isCheckbox($rc_field);
						
						if ($lastFormName != $thisFormName) {
							$formHdrClass = (isset($_GET['page']) && $_GET['page'] != $thisFormName) ? "rtws-otherform" : "";
							$rows .=	RCView::tr(array('class'=>"adjud_form_hdr $formHdrClass"),
											RCView::td(array('style'=>'border-top:2px solid #999;padding:5px;color:#222;background-color:#ccc;font-weight:bold;', 'colspan'=>($longitudinal ? 7 : 6)),
												RCView::escape($Proj->forms[$thisFormName]['menu'])
											)
										);
						}

						// Determine if this field exists on a locked form/event
						$isLocked = (isset($lockedFormsEvents[$this_event_id][$thisFormName][$this_instance]));
						
						// Is repeating form?					
						$isRepeatingForm = $Proj->isRepeatingForm($this_event_id, $thisFormName);

						// Set field name/label string
						$rc_field_label = 	$rc_field .
											RCView::span(array('style'=>'color:#666;font-size:7pt;'),
												" &nbsp;\"" . $Proj->metadata[$rc_field]['element_label'] . "\""
											);

						// If we're on a data entry form, then set a class for the table row for fields NOT on this form/event
						$onThisFormClass = (isset($form_data[$rc_field]) && $event_id == $this_event_id) ? "" : "rtws-otherform";
						if (isset($form_data[$rc_field]) && $this_instance > 0 && $instance != $this_instance) {
							$onThisFormClass = "rtws-otherform";
						}

						// Format the RC data value if a multiple choice field to display the option label
						if (!isset($rc_mapped_fields_choices[$rc_field]) || $fld_attr['rc_value'] == '') {
							// Display raw value
							$rc_value_formatted = $fld_attr['rc_value'];
						} else {
							// Display label and raw value
							$rc_value_formatted = $rc_mapped_fields_choices[$rc_field][$fld_attr['rc_value']] . " "
												. RCView::span(array('style'=>'color:#777;'), "(" . $fld_attr['rc_value'] . ")");
						}
						
						// Count the non-adjudicated values
						foreach ($fld_attr['src_fields'] as $subrow_src_field=>$subrow_src_attr) {							
							if (isset($subrow_src_attr['md_id'])) {
								// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.
								if (isset($non_adjudicated_values_by_md_id[$subrow_src_attr['md_id']])) {
									$totalUnadjudicatedValues++;
								}
								if (!$isRepeatingEvent && !$isRepeatingForm && isset($adjudicated_values_by_md_id[$subrow_src_attr['md_id']])) {
									$rcFieldsAdjudicatedValues["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
								}
							} else {						
								foreach ($subrow_src_attr as $subrow_src_attr2) {
									// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.
									if (isset($non_adjudicated_values_by_md_id[$subrow_src_attr2['md_id']])) {
										$totalUnadjudicatedValues++;
									}
									if (!$isRepeatingEvent && !$isRepeatingForm && isset($adjudicated_values_by_md_id[$subrow_src_attr2['md_id']])) {
										$rcFieldsAdjudicatedValues["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
									}
								}
							}
						}
						
						## NON-TEMPORAL (single row)
						if (!isset($fld_attr['rc_timestamp']) && !$isCheckbox)
						{
							// Get source field name and value
							$src_fields_keys = array_keys($fld_attr['src_fields']);
							$src_field = array_shift($src_fields_keys);
							$src_value = isset($fld_attr['src_fields'][$src_field]['src_value']) ? $fld_attr['src_fields'][$src_field]['src_value'] : '';
							$thisMdId = isset($fld_attr['src_fields'][$src_field]) ? $fld_attr['src_fields'][$src_field]['md_id'] : '';
							// If value is blank, then don't prompt user to overwrite an existing value with a blank one
							if ($src_value == '') continue;
							// Determine if the source value has been excluded
							$src_value_excluded = false;
							$src_value_excluded_action = '1';
							$src_value_excluded_text = RCView::img(array('src'=>'cross.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_87']));
							$src_value_excluded_class = '';
							if (isset($excluded_values_by_md_id[$thisMdId])) {
								$src_value_excluded = true;
								$src_value_excluded_action = '0';
								$src_value_excluded_text = RCView::img(array('src'=>'plus2.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_88']));
								$src_value_excluded_class = 'darkRedClr';
								$total_exclusions++;
								// Count the entire item as excluded if we're not showing exclusions
								if (!$show_excluded) $total_items_all_excluded++;
							}

							// If RC value and SRC value are the same, then add to array
							$rcAllFieldsEvents["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
							$preSelectRadio = "";
							$radioLinkVisibility = "hidden";
							$radioLinkBgColor = "";
							if ($src_value == $fld_attr['rc_value']) {
								$rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
							} elseif (!$isLocked && !$src_value_excluded) {
								$preSelectRadio = "checked";
								$radioLinkVisibility = "visible";
								$radioLinkBgColor = "radiogreen";
							}

							// Output the row
							if ($output_html && ($show_excluded || !$src_value_excluded))
							{
								// Set bg color for RC value cell if value exists in RC
								$rcValBgColor = '';
								if ($fld_attr['rc_value'] != '') {
									$rcValBgColor = ($src_value == $fld_attr['rc_value']) ? 'background-color:#C7ECC0;' : 'background-color:#ddd;';
								}

								// Set html for Exclude link
								$excludeLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"excludeValue($thisMdId,$src_value_excluded_action,$(this));", 'class'=>"reset $src_value_excluded_class", 'style'=>"font-size:8pt;text-decoration:underline;"), $src_value_excluded_text);

								// If field is on a locked form/event, then don't display radio button
								if ($isLocked) {
									$radioButton = $lockedSrcValueLabel;
								// If RC value is same as SRC value, then don't display radio button
								} elseif ($src_value == $fld_attr['rc_value']) {
									$radioButton = $sameSingleSrcValueLabel;
									$excludeLink = ''; // Don't show exclude link for existing values
									$radioLinkBgColor = "";
								} else {
									// Set radio button and hidden reset link for cell
									// If a date[time] field that's not in YMD format, then convert to YMD
									if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
										$src_value_radio = DateTimeRC::datetimeConvert($src_value, 'ymd', substr($thisValType, -3), 'ymd');
									} else {
										// Not a DMY/MDY date[time] field
										$src_value_radio = $src_value;
									}
									$radioButton = 	RCView::div(array('style'=>'float:left;padding-left:8px;'),
														RCView::radio(array('class'=>'rtws_adjud_radio', 'style'=>'visibility:'.($src_value_excluded ? 'hidden' : 'visible'), $preSelectRadio=>$preSelectRadio, 'name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field-$this_instance", 'value'=>$src_value_radio))
													) .
													RCView::div(array('style'=>'float:right;padding:2px 4px 0 0;'),
														RCView::a(array('href'=>'javascript:;', 'onclick'=>"radioResetValAdjud('rtws_adjud-$thisMdId-$this_event_id-$rc_field-$this_instance','rtws_adjud_form')", 'class'=>'reset opacity50', 'style'=>"visibility:$radioLinkVisibility;font-size:8pt;"), $lang['form_renderer_20'])
													);
								}

								// Format the source data value if a multiple choice field to display the option label
								if (!isset($rc_mapped_fields_choices[$rc_field])) {
									// Display raw value (non-MC field)
									$src_value_formatted = $src_value;
									// FIELD VALIDATION: If field has validation and value fails validation, then give error
									if (isset($valFields[$rc_field]) && !preg_match($valFields[$rc_field], $src_value)) {
										$radioButton = $invalidSrcValueLabel;
										$radioLinkBgColor = "";
									}
									// If data type is valid and has range validation, then check if within range
									elseif (isset($valFields[$rc_field]) && ($Proj->metadata[$rc_field]['element_validation_min'] != ''
										|| $Proj->metadata[$rc_field]['element_validation_max'] != ''))
									{
										if ($src_value < $Proj->metadata[$rc_field]['element_validation_min']
											|| $src_value > $Proj->metadata[$rc_field]['element_validation_max'])
										{
											$src_value_formatted .= $outOfRangeSrcValueLabel;
										}
									}
								} elseif (isset($rc_mapped_fields_choices[$rc_field][$src_value])) {
									// MC field: Display label and raw value
									$src_value_formatted = $rc_mapped_fields_choices[$rc_field][$src_value] . " "
														 . RCView::span(array('style'=>'color:#777;'), "($src_value)");
								} else {
									// Source value is NOT valid, so do not allow import and show error
									$src_value_formatted = $src_value;
									$radioButton = $invalidSrcValueLabel;
									$radioLinkBgColor = "";
								}
								
								// If non-temporal values have 
								$multipleValueWarning = "";
								$multipleValueWarningRadio = "";
								if (in_array($rc_field, $this->nonTemporalMultipleValueFields)) {
									$multipleValueWarning = RCView::div(array('style'=>'color:#C00000;line-height:11px;'), 
																'<i class="fas fa-exclamation-triangle"></i> ' . $lang['ws_245'] . 
																($Proj->isMultipleChoice($rc_field) ? " ".$lang['ws_247'] : "")
															);
									$multipleValueWarningRadio = RCView::div(array('style'=>'clear:both;color:#C00000;'), 
																'<i class="fas fa-exclamation-triangle"></i> ' . $lang['dataqueries_51']
															);
								}

								// Display single row
								$rows .= RCView::tr(array('class'=>"evtfld-$this_event_id rptinst-$this_repeat_instrument-$this_instance adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance $onThisFormClass", 'md_id'=>$thisMdId),
											(!$longitudinal ? '' :
												RCView::td(array('class'=>'odd', 'style'=>'line-height:11px;border-top:2px solid #999;'),
													$this_event_name
												)
											) .
											RCView::td(array('class'=>'odd', 'style'=>'vertical-align:top;padding:4px;border-top:2px solid #999;word-wrap:break-word;'),
												$rc_field_label . $multipleValueWarning
											) .
											RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'),
												" -"
											) .
											RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'),
												" -"
											) .
											RCView::td(array('class'=>'odd','style'=>'border-top:2px solid #999;line-height:13px;'.$rcValBgColor),
												$rc_value_formatted
											) .
											RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;border-top:2px solid #999;line-height:13px;'),
												nl2br($src_value_formatted)
											) .
											RCView::td(array('class'=>"odd $radioLinkBgColor",'style'=>'white-space:nowrap;border-top:2px solid #999;'),
												$radioButton . $multipleValueWarningRadio
											)
											/*
											. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;border-top:2px solid #999;'),
												$excludeLink
											)
											*/
										);
							}
						}
						## TEMPORAL or CHECKBOXES (possibly multiple rows)
						else
						{
							// Set flag noting that temporal fields will be displayed in the popup
							if (!$isCheckbox) $rtws_temporal_fields_displayed = 1;
							// Set which sub-row we're on, starting with 0
							$rc_field_loop = 0;
							// Count how many rows we'll need
							$rowspan = 0;
							foreach ($fld_attr['src_fields'] as $src_field=>$src_attr) {
								$rowspan += count($src_attr);
							}
							// Set flag for if this item contains at least one non-adjudicated value
							$hasNonAdjudicatedValues = 0;
							// Set array of src_fields concatenated with timestamp and value (so we can loop more easily through them all
							$this_row_src_fields = $this_row_sort_fields = $theseMdIds = array();
							foreach ($fld_attr['src_fields'] as $subrow_src_field=>$subrow_src_attr) {
								foreach ($subrow_src_attr as $subrow_src_attr2) {
									// Add each value's md_id to the array so that we have all md_id's for this item
									$theseMdIds[] = $subrow_src_attr2['md_id'];
									// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.
									if (isset($non_adjudicated_values_by_md_id[$subrow_src_attr2['md_id']])) {
										$hasNonAdjudicatedValues++;
									}
									// If timestamp is only a date, then append with 00:00
									$sort_timestamp = $subrow_src_attr2['src_timestamp'];
									if (!$isCheckbox && strlen($subrow_src_attr2['src_timestamp']) == 10) {
										$sort_timestamp .= ' 00:00';
									}
									// Add to array
									$this_row_src_fields[] = $subrow_src_attr2['src_timestamp']."|".$subrow_src_field."|".$subrow_src_attr2['md_id']."|".$subrow_src_attr2['src_value'];
									$this_row_sort_fields[] = $sort_timestamp."-".$subrow_src_attr2['src_value']."-".$subrow_src_field."-".$subrow_src_attr2['md_id'];
								}
							}
							// Sort values by timestamp
							array_multisort($this_row_sort_fields, SORT_REGULAR, $this_row_src_fields);

							## PRE-SELECTION
							if ($output_html && !$isCheckbox)
							{
								// Set preselection option value if field has more than 1 row
								$preselect = ($rowspan > 1) ? $fld_attr['rc_preselect'] : '';
								$preselect_value = ""; // Default
								## "NEAR" PRE-SELECT
								// If preselect option is MIN or MAX, then loop through all values first to determine min or max
								if ($preselect == 'NEAR') {
									// Get RC timestamp
									$rc_timestamp_string = strtotime($fld_attr['rc_timestamp']);
									// Place all numbers into an array
									$src_ts_values = array();
									for ($i = 0; $i < $rowspan; $i++) {
										// Get source field name, timestamp, and value
										list ($srcTS, $nothing2, $thisMdId2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
										// If the value is excluded for this item, then do not preselect it
										if (isset($excluded_values_by_md_id[$thisMdId2])) {
											continue;
										}
										// Add values to array and calculate proximity in time of each value
										$src_ts_values[abs($rc_timestamp_string-strtotime($srcTS))] = $this_src_value;
									}
									// Get closest value based on time proximity
									$smallestKey = min(array_keys($src_ts_values));
									$preselect_value = $src_ts_values[$smallestKey];
									// Remove array
									unset($src_ts_values);
								}
								## MIN/MAX PRE-SELECT
								// If preselect option is MIN or MAX, then loop through all values first to determine min or max
								elseif ($preselect == 'MIN' || $preselect == 'MAX') {
									// Place all numbers into an array
									$src_value_numbers = array();
									for ($i = 0; $i < $rowspan; $i++) {
										// Get source field name, timestamp, and value
										list ($nothing1, $nothing2, $thisMdId2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
										// If the value is excluded for this item, then do not preselect it
										if (isset($excluded_values_by_md_id[$thisMdId2])) {
											continue;
										}
										// If value is numerical, then add to number array
										if (is_numeric($this_src_value)) {
											$src_value_numbers[] = $this_src_value;
										}
									}
									// Get the preselected value (min or max) for this item
									$preselect_value = ($preselect == 'MIN') ? min($src_value_numbers) : max($src_value_numbers);
									// Remove array
									unset($src_value_numbers);
								}
								## SAME DAY PRE-SELECT
								// If not value is pre-selected BUT only one value falls on the same day as the REDCap date, then pre-select it
								elseif ($preselect == '') {
									$sameDateCount = 0;
									//print "\n\n$rc_field:";
									for ($i = 0; $i < $rowspan; $i++) {
										// Get source field name, timestamp, and value
										list ($this_src_timestamp, $nothing1, $nothing2, $this_src_value) = explode("|", $this_row_src_fields[$i], 4);
										// If the src and RC dates are the same, then pre-select it
										if (substr($this_src_timestamp, 0, 10) == substr($fld_attr['rc_timestamp'], 0, 10)) {
											// Get this date's value and increment counter
											$sameDateValue = $this_src_value;
											$sameDateCount++;
										}
									}
									// If only one day matched the RC date, then pre-select that one
									if ($sameDateCount == 1) {
										$preselect_value = $sameDateValue;
									}
								}
							}

							// Keep track of number of exclusions for this item
							$excluded_mdids = array_intersect($theseMdIds, array_keys($excluded_values_by_md_id));
							$total_exclusions_this_item = count($excluded_mdids);
							// PRE-SELECTION: If item should have LAST value pre-selected, then set that value now so we know which it is when we get to it
							if ($output_html && $preselect == 'LAST') {
								if ($total_exclusions_this_item == 0) {
									// Since there are no exclusions, just get the very last value
									list ($nothing1, $nothing2, $nothing3, $preselect_value) = explode("|", $this_row_src_fields[$rowspan-1], 4);
								} else {
									// Since exclusions exist, first remove excluded values before we can determine the last one
									$non_excluded_values_this_item = array();
									for ($i = 0; $i < $rowspan; $i++) {
										$excluded_values_this_item_temp = explode("|", $this_row_src_fields[$i], 4);
										$thisMdId3 = $excluded_values_this_item_temp[2];
										if (!in_array($thisMdId3, $excluded_mdids)) {
											$non_excluded_values_this_item[$thisMdId3] = $excluded_values_this_item_temp[3];
										}
									}
									$preselect_value = array_pop($non_excluded_values_this_item);
								}
							}

							// Add formatting to src timestamp
							if ($isCheckbox) {
								$rc_timestamp_formatted = "";
							} elseif (strpos($fld_attr['rc_timestamp'], " ") !== false) {
								// REDCap Date/Time
								list ($rc_timestamp1, $rc_timestamp2) = explode(" ", $fld_attr['rc_timestamp'], 2);
								$rc_timestamp_formatted = $rc_timestamp1 . RCView::span(array('class'=>'adjud_ts'), $rc_timestamp2);
							} else {
								// REDCap Date, so add "(00:00)" to show that it is assumed to use midnight as reference point
								$rc_timestamp_formatted = $fld_attr['rc_timestamp'] . RCView::span(array('class'=>'adjud_ts'), '(00:00)');
							}

							// Set flag to note which value is the first non-excluded value for this item.
							// Default as false, then true for first non-excluded value, then Null for any values after being true.
							$is_first_nonexcluded_value_this_item = false;

							// Loop through all subrows/values
							for ($i = 0; $i < $rowspan; $i++)
							{
								// Get source field name, timestamp, and value
								list ($src_timestamp, $src_field, $thisMdId, $src_value) = explode("|", $this_row_src_fields[$i], 4);
								
								// Checkboxes only: Deal with array of values to show if choice is checked for saved project value
								if ($isCheckbox) {
									$rc_value_formatted = "";
									if ($fld_attr['rc_value'][$src_value] == '1') {
										$rc_value_formatted = $rc_mapped_fields_choices[$rc_field][$src_value] . " "
															. RCView::span(array('style'=>'color:#777;'), "(" . $src_value . ")");
									}
								}

								// If RC value and SRC value are the same, then add to array
								$rcAllFieldsEvents["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
								if (((!$isCheckbox && $src_value == $fld_attr['rc_value']) 
									|| ($isCheckbox && isset($fld_attr['rc_value'][$src_value]) && $fld_attr['rc_value'][$src_value] == '1'))
									// If this item has at least one value that's not been adjudicated (get imported after other values were adjudicated),
									// then show this item so that user has a chance to see the new value (in case they need to import it and
									// overwrite the existing value).
									&& !($hasNonAdjudicatedValues > 0 && $hasNonAdjudicatedValues < $rowspan))
								{
									$rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"] = true;
								}

								// If we have already noted the first non-excluded value for this item, then set this to null.
								if ($is_first_nonexcluded_value_this_item === true) {
									$is_first_nonexcluded_value_this_item = null;
								}
								// Determine if the source value has been excluded
								$src_value_excluded = false;
								$src_value_excluded_action = '1';
								$src_value_excluded_text = RCView::img(array('src'=>'cross.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_87']));
								$src_value_excluded_class = '';
								if (isset($excluded_values_by_md_id[$thisMdId])) {
									$src_value_excluded = true;
									$src_value_excluded_action = '0';
									$src_value_excluded_text = RCView::img(array('src'=>'plus2.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_88']));
									$src_value_excluded_class = 'darkRedClr';
									$total_exclusions++;
									// Count the entire item as excluded if we're not showing exclusions (only do this for very last value of this item)
									if (!$show_excluded && $i == ($rowspan-1) && $total_exclusions_this_item >= $rowspan) $total_items_all_excluded++;
								}
								// If this is the first non-excluded value for this item, set flag to true.
								elseif ($is_first_nonexcluded_value_this_item === false) {
									$is_first_nonexcluded_value_this_item = true;
								}

								if ($output_html && ($show_excluded || !$src_value_excluded))
								{
									// Set attributes to pre-select field if only has one value returned
									if (!$isLocked && (($rowspan == 1 && $src_value != $fld_attr['rc_value'])
										// If ealiest value should be preselected
										|| ($preselect == 'FIRST' && $is_first_nonexcluded_value_this_item === true)
										// If latest value should be preselected
										|| ($preselect == 'LAST' && $src_value == $preselect_value))
									) {
										$preSelectRadio = "checked";
										$radioLinkVisibility = "visible";
										$radioLinkBgColor = "radiogreen";
										// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical
										if ($preselect == 'LAST' && $src_value == $preselect_value) {
											$preselect_value = '';
										}
									// If max or min or nearest value should be preselected AND this is that value, then preselect it
									} elseif (($preselect == 'MIN' || $preselect == 'MAX' || $preselect == 'NEAR' || $preselect == '') && $src_value == $preselect_value) {
										$preSelectRadio = "checked";
										$radioLinkVisibility = "visible";
										$radioLinkBgColor = "radiogreen";
										// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical
										$preselect_value = '';
									} else {
										$preSelectRadio = "";
										$radioLinkVisibility = ($src_value_excluded ? "hidden" : "visible");
										$radioLinkBgColor = "";
									}

									if ($isCheckbox) {
										$preSelectRadio = "checked";
										$radioLinkVisibility = "visible";
										$radioLinkBgColor = "radiogreen";
									}
									// If existing value matches a src value already, then do NOT preselect an option
									elseif (isset($rcFieldsSameValue["adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance"])) {
										$preselect_value = '';
										$preSelectRadio = "";
										$radioLinkVisibility = "hidden";
										$radioLinkBgColor = "";
									}
									
									//print "hasNonAdjudicatedValues: $hasNonAdjudicatedValues\n";

									// print "\n$rc_field, $preselect, $preselect_value, $src_value == $preselect_value";

									// If this item contains any non-adjudicated values, then make sure nothing gets preselected
									if ($hasNonAdjudicatedValues > 0 && $hasNonAdjudicatedValues < $rowspan) $preselect_value = '';

									// Add formatting to src timestamp
									if (!$isCheckbox) {
										if (strpos($src_timestamp, " ") !== false) {
											list ($src_timestamp1, $src_timestamp2) = explode(" ", $src_timestamp, 2);
											$src_timestamp_formatted = $src_timestamp1 .
																		RCView::span(array('class'=>'adjud_ts'), substr($src_timestamp2, 0, 5));
										} else {
											$src_timestamp_formatted = $src_timestamp;
										}
									}

									// Set html for Exclude link
									$excludeLink = RCView::a(array('href'=>'javascript:;', 'onclick'=>"excludeValue($thisMdId,$src_value_excluded_action,$(this));", 'class'=>"reset $src_value_excluded_class", 'style'=>"font-size:8pt;text-decoration:underline;"), $src_value_excluded_text);

									// If field is on a locked form/event, then don't display radio button
									if ($isLocked) {
										$radioButton = $lockedSrcValueLabel;
									// If RC value is same as SRC value, then don't display radio button
									} elseif ($src_value == $fld_attr['rc_value'] || ($isCheckbox && $rc_value_formatted != "")) {
										$radioButton = $sameSingleSrcValueLabel;
										// Don't show exclude link for existing values
										$excludeLink = '';
										$radioLinkBgColor = "";
										// If this item contains any non-adjudicated values, then add a hidden input with the value so that
										// any non-adjudicated values will get set as adjudicated upon save (otherwise the item will just keep showing up).
										if ($hasNonAdjudicatedValues > 0) {
											$radioButton .= RCView::hidden(array('name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field-$this_instance", 'value'=>$src_value));
										}
									} else {
										// Set radio button and hidden reset link for cell
										// If a date[time] field that's not in YMD format, then convert to YMD
										if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
											$src_value_radio = DateTimeRC::datetimeConvert($src_value, 'ymd', substr($thisValType, -3));
										} else {
											// Not a DMY/MDY date[time] field
											$src_value_radio = $src_value;
										}
										$radioButton = 	RCView::div(array('style'=>'float:left;padding-left:8px;'),
															RCView::radio(array('class'=>'rtws_adjud_radio', 'style'=>'visibility:'.($src_value_excluded ? 'hidden' : 'visible'), $preSelectRadio=>$preSelectRadio, 'name'=>"rtws_adjud-$thisMdId-$this_event_id-$rc_field-$this_instance", 'value'=>$src_value_radio))
														) .
														RCView::div(array('style'=>'float:right;padding:2px 4px 0 0;'),
															RCView::a(array('href'=>'javascript:;', 'onclick'=>"radioResetValAdjud('rtws_adjud-$thisMdId-$this_event_id-$rc_field-$this_instance','rtws_adjud_form')", 'class'=>'reset opacity50', 'style'=>"visibility:$radioLinkVisibility;font-size:8pt;"), $lang['form_renderer_20'])
														);
									}

									// Format the source data value if a multiple choice field to display the option label
									if (!isset($rc_mapped_fields_choices[$rc_field])) {
										// Display raw value (non-MC field)
										$src_value_formatted = $src_value;
										// FIELD VALIDATION: If field has validation and value fails validation, then give error
										if (isset($valFields[$rc_field]) && !preg_match($valFields[$rc_field], $src_value))
										{
											$radioButton = $invalidSrcValueLabel;
											$radioLinkBgColor = "";
										}
										// If data type is valid and has range validation, then check if within range
										elseif (isset($valFields[$rc_field]) && ($Proj->metadata[$rc_field]['element_validation_min'] != ''
											|| $Proj->metadata[$rc_field]['element_validation_max'] != ''))
										{
											if ($src_value < $Proj->metadata[$rc_field]['element_validation_min']
												|| $src_value > $Proj->metadata[$rc_field]['element_validation_max'])
											{
												$src_value_formatted .= $outOfRangeSrcValueLabel;
											}
										}
									} elseif (isset($rc_mapped_fields_choices[$rc_field][$src_value])) {
										// Display label and raw value
										$src_value_formatted = $rc_mapped_fields_choices[$rc_field][$src_value] . " "
															 . RCView::span(array('style'=>'color:#777;'), "($src_value)");
									} else {
										// Source value is NOT valid, so do not allow import and show error
										$src_value_formatted = $src_value;
										$radioButton = $invalidSrcValueLabel;
										$radioLinkBgColor = "";
									}

									// Make cell for source date green if occurs on same day as REDCap date
									$srcTsBgColor = (!$isCheckbox && substr($fld_attr['rc_timestamp'], 0, 10) == substr($src_timestamp, 0, 10)) ? 'background-color:#C7ECC0;' : '';
									$rcValBgColor = '';
									if ((!$isCheckbox && $fld_attr['rc_value'] != '') || ($isCheckbox && $rc_value_formatted != "")) {
										$rcValBgColor = ($src_value == $fld_attr['rc_value']) ? 'background-color:#C7ECC0;' : 'background-color:#ddd;';
									}
									
									$chkboxClass = $isCheckbox ? "chkbx" : "";

									## Display first row (or display the first non-excluded value/row for this item if hiding excluded values)
									if ($i == 0 || (!$show_excluded && $is_first_nonexcluded_value_this_item === true)) {
										$rows .= RCView::tr(array('class'=>"$chkboxClass evtfld-$this_event_id rptinst-$this_repeat_instrument-$this_instance adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance $onThisFormClass", 'md_id'=>$thisMdId),
													(!$longitudinal ? '' :
														RCView::td(array('class'=>'odd', 'style'=>'line-height:11px;border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
															$this_event_name
														)
													) .
													RCView::td(array('class'=>'odd', 'style'=>'vertical-align:top;padding:4px;word-wrap:break-word;border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
														$rc_field_label
													) .
													RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;', 'rowspan'=>($rowspan-($show_excluded ? 0 : $total_exclusions_this_item)), 'valign'=>'top'),
														$rc_timestamp_formatted
													) .
													RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'.$srcTsBgColor.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
														$src_timestamp_formatted
													) .
													RCView::td(array('class'=>'odd', 'style'=>'border-top:2px solid #999;'.$rcValBgColor.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
														$rc_value_formatted
													) .
													RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;border-top:2px solid #999;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
														$src_value_formatted
													) .
													RCView::td(array('class'=>"odd $radioLinkBgColor", 'style'=>'border-top:2px solid #999;white-space:nowrap;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
														$radioButton
													)
													/*
													. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;border-top:2px solid #999;'.($rowspan == 1 ? '' : 'border-bottom:1px solid #eee;')),
														$excludeLink
													)
													*/
												);

									}
									## Display non-first rows
									else {
										$rows .= RCView::tr(array('class'=>"$chkboxClass evtfld-$this_event_id rptinst-$this_repeat_instrument-$this_instance adjud_tr-$this_event_id-$rc_field-$this_repeat_instrument-$this_instance $onThisFormClass", 'md_id'=>$thisMdId),
													RCView::td(array('class'=>'odd', 'style'=>$srcTsBgColor),
														$src_timestamp_formatted
													) .
													RCView::td(array('class'=>'odd', 'style'=>$rcValBgColor),
														$rc_value_formatted
													) .
													RCView::td(array('class'=>'odd', 'style'=>'color:#C00000;'),
														$src_value_formatted
													) .
													RCView::td(array('class'=>"odd $radioLinkBgColor", 'style'=>'white-space:nowrap;'),
														$radioButton
													)
													/*
													. RCView::td(array('class'=>'odd', 'style'=>'text-align:center;'),
														$excludeLink
													)
													*/
												);
									}
								}
							}
							// Increment $rc_field_loop
							$rc_field_loop++;
						}						
						$lastFormName = $thisFormName;
					}
				}
			}			
			$last_event_id = $this_event_id;
		}

		// Count the number of items that need to be adjudicated
		$itemsToAdjudicate = (count($rcAllFieldsEvents) - $total_items_all_excluded 
						   - count(array_unique(array_merge(array_keys($rcFieldsAdjudicatedValues), array_keys($rcFieldsSameValue)))));

		// Set table html
		$html = '';
		if ($output_html)
		{
			// If ALL rows in table are HIDDEN, then give a note
			// if ($itemsToAdjudicate == 0) {

			// }

			// Get the last time data was fetched
			$lastFetchTimeText = $this->getLastFetchTime($record, true);

			$mrnDisplay = "";
            if ($this->realtime_webservice_type == 'FHIR') {
                // Get the REDCap field name and event_id of the external identifier
                list ($rc_field_external, $rc_event_external) = $this->getMappedIdRedcapFieldEvent();
                // Get MRN via getData
                $mrnData = Records::getData($this->project_id, 'array', array($record), array($rc_field_external), array($rc_event_external));
                if (isset($mrnData[$record][$rc_event_external][$rc_field_external])) {
                    $mrnDisplay = RCView::span(array('style'=>'vertical-align:middle;margin-left:50px;color:#C00000;font-size:14px;font-weight:normal;'),
                        "MRN: ".RCView::span(array('class'=>'boldish'), $mrnData[$record][$rc_event_external][$rc_field_external]));
                }
            }

			// Add row above table header to display number of new and hidden items
			$itemNumBar =   RCView::div(array('style'=>'border:1px solid #ccc;border-bottom:0;border-top:0;padding:10px;background-color:#eee;max-width:100%;'),
							// New items
							RCView::div(array('style'=>'padding-top:3px;float:left;font-weight:bold;font-size:14px;'),
								RCView::span(array('style'=>'vertical-align:middle;'), "New items:") .
                                    RCView::span(array('style'=>'vertical-align:middle;margin-left:7px;color:#C00000;font-size:18px;'), $itemsToAdjudicate) .
                                    RCView::span(array('style'=>'vertical-align:middle;margin-left:50px;color:#777;font-size:13px;font-weight:normal;'),
                                        $lang['ws_141'] . RCView::SP . $lastFetchTimeText
                                    ) .
                                    $mrnDisplay
							) .
							// Button to limit to filter to fields on this form (ONLY IF on a form)
							(!(!empty($form_data) && is_numeric($event_id)) ? '' :
								RCView::div(array('style'=>'float:left;margin-left:50px;color:#555;font-size:12px;font-weight:normal;'),
									RCView::div(array('style'=>''),
										RCView::radio(array('name'=>'rtws-otherformfields-radio', 'value'=>'allforms', 'checked'=>'checked', 'onclick'=>"hideOtherFormFields(0)")) .
										$lang['ws_142'] . " " .
										($longitudinal ? $lang['ws_155'] : $lang['ws_156'])
									) .
									RCView::div(array('style'=>''),
										RCView::radio(array('name'=>'rtws-otherformfields-radio', 'value'=>'thisform', 'onclick'=>"hideOtherFormFields(1)")) .
										$lang['ws_143']
									)
								)
							) .
							RCView::div(array('style'=>'float:right;text-align:right;'),
								// Hidden Items buttons
								(empty($rcFieldsSameValue) ? '' :
									RCView::button(array('id'=>'btn_existing_items_show', 'log_view'=>'1', 'class'=>'jqbuttonmed', 'style'=>'font-size:11px;color:#000066;margin-left:10px;', 'onclick'=>"showHiddenAdjudRows(true); return false;"),
										$lang['global_84']." ".count($rcFieldsSameValue)." ".$lang['ws_145']
									) .
									RCView::button(array('id'=>'btn_existing_items_hide', 'class'=>'jqbuttonmed', 'style'=>'display:none;font-size:11px;color:#000066;margin-left:10px;', 'onclick'=>"showHiddenAdjudRows(false); return false;"),
										$lang['ws_144']." ".count($rcFieldsSameValue)." ".$lang['ws_146']
									)
								) .
								// Exclusions button
								(($total_exclusions == 0 || $show_excluded) ? '' :
									(empty($rcFieldsSameValue) ? '' : RCView::br()) .
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;color:#C00000;margin-left:10px;', 'onclick'=>"
										if (confirmRefetchSourceData()) {
											triggerRTWSmappedField( $('#rtws_adjud_popup_recordname').text(), true, 1, true);
										}
										return false;
									"),
										$lang['global_84']." $total_exclusions ".$lang['ws_147']
									)
								)
							) .
							RCView::div(array('class'=>'clear'), '')
						);

			// Table header
			$rows = RCView::tr(array('class'=>($itemsToAdjudicate != 0 ? '' : 'hidden')),
						(!$longitudinal ? '' :
							RCView::th(array('class'=>'header', 'style'=>'padding:10px;width:100px;'),
								$lang['ws_148']
							)
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px;'),
							$lang['ws_149']
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:108px;'),
							$lang['ws_150']
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:108px;'),
							RCView::div(array('style'=>'color:#C00000;'), self::getSourceSystemName()) .
							RCView::div(array(), $lang['ws_151'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:150px;'),
							RCView::div(array(), "REDCap") .
							RCView::div(array(), $lang['ws_152'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'padding:10px 5px;width:150px;'),
							RCView::div(array('style'=>'color:#C00000;'), self::getSourceSystemName()) .
							RCView::div(array(), $lang['ws_153'])
						) .
						RCView::th(array('class'=>'header', 'style'=>'font-size:13px;color:#000066;text-align:center;padding:10px 3px;width:75px;'),
							$lang['ws_154'] .
							RCView::div(array('style'=>'text-align:center;'),
								RCView::img(array('src'=>'go-down.png', 'style'=>'vertical-align:middle;'))
							)
						)
						/*
						. RCView::th(array('class'=>'header', 'style'=>'text-align:center;font-size:11px;font-weight:normal;padding:10px 3px;width:60px;'),
							RCView::div(array('class'=>'nowrap'),
								"Exclude" .
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:3px;', 'onclick'=>"rtwsExcludeExplain();"),
									RCView::img(array('src'=>'help.png', 'style'=>'vertical-align:middle;'))
								)
							)
						)
						*/
					) .
					// Table rows
					$rows;
					
			// Any errors?
			$errorsHTML = "";
			if(isset($this->fhirData) && $this->fhirData->hasErrors())
			{
				$errors = $this->fhirData->getErrors();

				$errorsHTML = "<div class='red' style='max-width:1200px;'><b><i class='fas fa-exclamation-triangle'></i> {$lang['global_03']}{$lang['colon']}</b> {$lang['ws_246']}<ul style='margin:0;'>";
				foreach ($errors as $error) {
					$errorMessage = $error->getMessage();
					if($detail = $error->getErrorDetail()) $errorMessage .= '<p>'.$detail.'</p>';
					$errorsHTML .= '<li>'.$errorMessage.'</li>';
				}
				$errorsHTML .= "</ul></div>";
			}

			// Form/table html
			$html .=	// Div to display number of items
						$itemNumBar . 
						$errorsHTML . 
						RCView::form(array('method'=>'post', 'name'=>'rtws_adjud_form', 'id'=>'rtws_adjud_form', 'style'=>'margin:0 0 20px;', 'action'=>APP_PATH_WEBROOT."DynamicDataPull/index.php?pid=".$this->project_id, 'enctype'=>'multipart/form-data'),
							// Table
							RCView::table(array('id'=>'adjud_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;width:100%;'), $rows) .
							// Hidden field for REDCap mapped ID field
							RCView::hidden(array('id'=>'hidden_source_id_value', 'name'=>"rtws_adjud--$external_id_rc_event-$external_id_rc_field-1", "value"=>$external_id_value)) .
							// Hidden field to denote if this record exists already (to deal with record auto-numbering)
							RCView::hidden(array('name'=>"rtws_adjud-record_exists", "value"=>$record_exists)) .
							// Set list of md_id values that came in the data feed but are out of range. Will exclude these from being adjudicated (for repeating forms).
							RCView::hidden(array('name'=>"md_ids_out_of_range", "value"=>implode(",", $md_ids_out_of_range)))
						);

			// HIDDEN ELEMENTS INSIDE DIALOG
			// Render an invisible div containing the event_id-rc_fieldname of all rows to hide
			$html .= RCView::div(array('id'=>'adjud_tr_classes', 'class'=>'hidden'), 
						implode(",", array_merge(array_keys($rcFieldsSameValue), array_keys($rcFieldsAdjudicatedValues)))
					);
			// Place flag if any temporal fields are being displayed (so we can hide certain labels if they are not)
			$html .= RCView::hidden(array('id'=>"rtws_temporal_fields_displayed", "value"=>$rtws_temporal_fields_displayed));
		}

		// Set the new item count for this record in the table
		$sql = "update redcap_ddp_records set item_count = $itemsToAdjudicate
				where record = '" . db_escape($record) . "' and project_id = " . $this->project_id;
		$q = db_query($sql);

		// Return $itemsToAdjudicate and all html as array
		return array($itemsToAdjudicate, $html);
	}


	/**
	 * GET NEW ITEM COUNT FOR A RECORD
	 * Returns integer for count (if not exists, returns null)
	 */
	private function getNewItemCount($record)
	{
		$item_count = null;
		$sql = "select item_count from redcap_ddp_records
				where record = '" . db_escape($record) . "' and project_id = " . $this->project_id;
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			$item_count = db_result($q, 0);
		}
		// Return count is exists, else return null
		return (is_numeric($item_count) ? $item_count : null);
	}


	/**
	 * LOG ALL THE SOURCE DATA POINTS (MD_ID'S) VIEWED BY THE USER
	 * Returns nothing.
	 */
	public function logDataView($external_id_value=null, $md_ids_viewed=array())
	{
		// Keep count of data points logged
		$num_logged = 0;
		if (!empty($md_ids_viewed))
		{
			// Get the timestamp and external source field names for the md_id's
			$md_ids_data = array();
			$sql = "select d.md_id, m.external_source_field_name, d.source_timestamp
					from redcap_ddp_records_data d, redcap_ddp_mapping m
					where m.map_id = d.map_id and d.md_id in (".prep_implode(array_unique($md_ids_viewed)).")
					and m.project_id = ".$this->project_id."";
			$q = db_query($sql);
			if (db_num_rows($q) < 1) return $num_logged;
			while ($row = db_fetch_assoc($q)) {
				$md_ids_data[$row['md_id']] = array('field'=>$row['external_source_field_name'], 'timestamp'=>$row['source_timestamp']);
			}
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".db_escape($external_id_value)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($md_ids_data as $md_id=>$attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp, md_id)
							values ($ml_id, '".db_escape($attr['field'])."', ".checkNull($attr['timestamp']).", $md_id)";
					if (db_query($sql)) $num_logged++;
				}
				// If somehow no data points were logged, then remove this instance from logging table
				if ($num_logged == 0) {
					$sql = "delete from redcap_ddp_log_view where ml_id = $ml_id";
					db_query($sql);
				}
			}
		}
		// Return count of data points that were logged as having been viewed
		return $num_logged;
	}


	/**
	 * Determine if a date[time] falls within a window of time by using a base date[time] +- offset
	 */
	private static function dateInRange($dateToCheck, $dateBase, $dayOffset, $day_offset_plusminus)
	{
		// Convert day_offset to seconds for comparison
		$dayOffsetSeconds = $dayOffset*86400;
		// Check if in range, which is dependent upon offset_plusminus value
		if ($day_offset_plusminus == '+-') {
			$diff = abs(strtotime($dateToCheck) - strtotime($dateBase));
			return ($diff <= $dayOffsetSeconds);
		} elseif ($day_offset_plusminus == '+') {
			$diff = strtotime($dateToCheck) - strtotime($dateBase);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} elseif ($day_offset_plusminus == '-') {
			$diff = strtotime($dateBase) - strtotime($dateToCheck);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} else {
			return false;
		}
	}

	/**
	 * PARSE AND SAVE DATA SUBMITTED AFTER BEING ADJUDICATED
	 */
	public function saveAdjudicatedData($record, $event_id, $form_data)
	{
		global $table_pk, $table_pk_label, $Proj, $auto_inc_set, $lang;

		// If using record auto-numbering and record does not exist yet, then update $record with next record name (in case current one was already saved by other user)
		if (isset($form_data['rtws_adjud-record_exists']) && $form_data['rtws_adjud-record_exists'] == '0' && $auto_inc_set) {
			$record = DataEntry::getAutoId();
		}
		if (isset($form_data['md_ids_out_of_range']) && trim($form_data['md_ids_out_of_range']) != '') {
			$md_ids_out_of_range = array_unique(explode(",", $form_data['md_ids_out_of_range']));
		}
		unset($form_data['rtws_adjud-record_exists'], $form_data['md_ids_out_of_range']);
		
		// Parse submitted form data into array of new/changed values. Save with record, event_id, field as 1st, 2nd, and 3rd-level array keys.
		$data_to_save = $md_ids = array();
		foreach ($form_data as $key=>$val) {
			// Explode the key
			list ($nothing, $md_id, $rc_event, $rc_field, $instance) = explode("-", $key, 5);
			if ($instance < 1 || !is_numeric($instance)) $instance = 1;
			// Add md_id to array so we can set this value as "adjudicated" in the mapping_data table
			if ($md_id != '') $md_ids[] = $md_id;
			// Add to rc data array
			$val = str_replace("\t", "", $val);
			if ($val != '') {
				// If value is blank, then do not save it (only sent it to mark it as adjudicated)
				if ($Proj->isCheckbox($rc_field)) {
					$data_to_save[$record][$rc_event][$instance]["__chk__{$rc_field}_RC_".DataEntry::replaceDotInCheckboxCoding($val)] = $val;
				} else {
					$data_to_save[$record][$rc_event][$instance][$rc_field] = $val;
				}
			}
		}
		$md_ids = array_unique($md_ids);

		// Set string of javascript to execute if on a data entry form to update DOM
		$adjudJsUpdate = '';

		// Get the REDCap field name and event_id of the external identifier
		list ($rc_field_external, $rc_event_external) = $this->getMappedIdRedcapFieldEvent();

		// Keep count of number of items saved
		$itemsSaved = 0;
		
		// Note the current instance, if any
		$currentInstanceOrig = (is_numeric($_GET['event_id']) && is_numeric($_GET['instance'])) ? $_GET['instance'] : null;

		// Loop through each event of data and save it using DataEntry::saveRecord() function
		foreach ($data_to_save as $this_record=>$event_data) {
			foreach ($event_data as $this_event_id=>$idata) {
				foreach ($idata as $this_instance=>$field_data) {
					// Simulate new Post submission (as if submitted via data entry form)
					$_POST = array_merge(array($table_pk=>$this_record), $field_data);
					// Need event_id and instance in query string for saving properly
					$_GET['event_id'] = $this_event_id;
					$_GET['instance'] = $this_instance;
					// Delete randomization field values and log it
					DataEntry::saveRecord($this_record);
					// Add javascript to string for eval'ing (only on data entry form)
					if ($event_id != '') {
						if ($this_event_id == $event_id 
							&& ($currentInstanceOrig == null || ($currentInstanceOrig != null && $currentInstanceOrig == $this_instance))
						) {
							## CURRENT EVENT
							// Loop through all fields in event
							foreach ($field_data as $this_field=>$this_value) {
								// Increment number of items saved
								if ($this_field != $table_pk && $this_field != $rc_field_external) {
									$itemsSaved++;
								}
								// Parse out the field name and the checkbox coded value
								if (substr($this_field, 0, 7) == '__chk__') {
									$checkboxInputName = $this_field;
									list ($this_field, $chkval) = explode('_RC_', substr($this_field, 7), 2);
									$chkval = DataEntry::replaceDotInCheckboxCodingReverse($chkval);
								}
								// Get field type
								$thisFieldType = $Proj->metadata[$this_field]['element_type'];
								// CHECKBOX
								if ($thisFieldType == 'checkbox') {
									// Hidden input
									$adjudJsUpdate .= "$('form#form :input[name=\"$checkboxInputName\"]').val(\"".js_escape2($this_value)."\");";
									// Checkbox input
									$adjudJsUpdate .= "$('form#form #id-$checkboxInputName').prop('checked', true);";
								}
								// RADIO (including YesNo and TrueFalse)
								elseif ($thisFieldType == 'radio' || $thisFieldType == 'yesno' || $thisFieldType == 'truefalse') {
									$adjudJsUpdate .= "$('form#form :input[name=\"$this_field\"]').val('".js_escape($this_value)."');";
									// Make sure to select the ___radio input as well
									$adjudJsUpdate .= "$('form#form :input[name=\"{$this_field}___radio\"][value=\"".js_escape2($this_value)."\"]').prop('checked', true);";
								}
								// NOTES BOX
								elseif ($thisFieldType == 'textarea') {
									$adjudJsUpdate .= "$('form#form textarea[name=\"$this_field\"]').val(".json_encode_rc($this_value).");";
								}
								// DROPDOWN
								elseif ($thisFieldType == 'select') {
									$adjudJsUpdate .= "$('form#form select[name=\"$this_field\"]').val('".js_escape($this_value)."');";
									// Also set autocomplete input for drop-down (if using auto-complete)
									$adjudJsUpdate .=  "if ($('#form #rc-ac-input_$this_field').length)
															$('#form #rc-ac-input_$this_field').val( $('#form select[name=\"$this_field\"] option:selected').text() );";
								}
								// ALL OTHER FIELD TYPES
								else {
									$adjudJsUpdate .= "$('form#form :input[name=\"$this_field\"]').val('".js_escape($this_value)."');";
								}
							}
						} else {
							## DIFFERENT EVENT DATA (might be hidden in other form on page)
							// Get unique event name for this event
							$this_unique_event_name = $Proj->getUniqueEventNames($this_event_id);
							// Only execute this chunk if this form exists
							$adjudJsUpdate .= " if ($('form[name=\"form__{$this_unique_event_name}\"]').length) { ";
							// Loop through all fields in event
							foreach ($field_data as $this_field=>$this_value) {
								$adjudJsUpdate .= "$('form[name=\"form__{$this_unique_event_name}\"] input[name=\"$this_field\"]').val('".js_escape($this_value)."');";
							}
							// End the IF statement
							$adjudJsUpdate .= " } ";
						}
					}
				}
			}
		}

		// collect statistics about the new FHIR data
		$fhir_stats_collector = new FhirStatsCollector($Proj->project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDP);
		$entries = $fhir_stats_collector->addEntriesFromRecord($data_to_save);
		$fhir_stats_collector->logEntries();

		// Set all adjudicated items as "adjudicated" in mapping_data table (include all values for a given item/field that was adjudicated)
		$sql = "";
		if (!empty($md_ids)) 
		{
			$sql = "update redcap_ddp_records_data a, redcap_ddp_mapping b,
					redcap_ddp_mapping c, redcap_ddp_records_data d, redcap_ddp_records e
					set d.adjudicated = 1 where a.map_id = b.map_id and b.field_name = c.field_name and b.event_id = c.event_id
					and d.map_id = c.map_id and d.mr_id = e.mr_id and e.mr_id = a.mr_id
					and a.md_id in (" . prep_implode($md_ids) . ")";
			if (!empty($md_ids_out_of_range)) {
				$sql .= " and d.md_id not in (" . prep_implode($md_ids_out_of_range) . ")";
			}
			$q = db_query($sql);
		}

		// If on a data entry form, generate JS needed to update fields on the currently open page.
		// Put all the JS inside a hidden div to be eval'd.
		$adjudJsUpdate =   "$(function(){
								$adjudJsUpdate
								var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
								if (isEHRpage || page == 'DataEntry/record_home.php') {
									triggerRTWSmappedField('".js_escape($record)."',false,true,false,true);
								} else if (page == 'DataEntry/index.php') {
									$('#form :input[name=\"$table_pk\"], #form :input[name=\"__old_id__\"]').val('".js_escape($record)."');
									$('#form :input[name=\"hidden_edit_flag\"]').val('1');
									var numItemsBefore = $('#RTWS_sourceDataCheck .badgerc:first').text()*1;
									var numItemsAfter = (numItemsBefore - $itemsSaved);
									$('#RTWS_sourceDataCheck .badgerc:first').html(numItemsAfter);
									try { calculate(); doBranching(); } catch(e) { }
									autoCheckNewItemsFromSource('".js_escape($record)."', false);
								} else if (page == 'DataEntry/record_status_dashboard.php') {
									$('#rtws_new_items-".js_escape($record)."').removeClass('data').removeClass('statusdashred').addClass('darkgreen').html(recordProgressIcon);
									triggerRTWSmappedField('".js_escape($record)."',false,false);
								}
						    });";
		// Render div containing javascript
		print RCView::div(array('id'=>'adjud_js_set_form_values', 'class'=>'hidden'), $adjudJsUpdate);

		// Return confirmation message in pop-up
		?>
		<div class="darkgreen" style="margin:10px 0;">
			<table cellspacing=10 width=100%>
				<tr>
					<td style="padding:0 30px 0 40px;">
						<img src="<?php echo APP_PATH_IMAGES ?>check_big.png">
					</td>
					<td style="font-size:14px;font-family:verdana;line-height:22px;padding-right:30px;">
						<?php
						print "<b>{$lang['ws_157']}</b><br>{$lang['ws_158']} $table_pk_label \"<b>$record</b>\"!"
						?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
	/**
	 * CALL "METADATA" WEB SERVICE TO OBTAIN LIST OF EXTERNAL SOURCE FIELDS
	 * Return metadata as array with unique field name as array keys and long name as values.
	 * If not JSON encoded, then return FALSE.
	 */
	public function getExternalSourceFields()
	{
		// Get global var
		global $realtime_webservice_url_metadata, $lang;
		// If using built-in FHIR service, call internal methods
		if ($this->realtime_webservice_type == 'FHIR') {
			$metadata_array = self::getFhirMetadata();
		}		
		// Call the custom metadata web service URL as POST request
		else {
			$params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL);
			$metadata_json = http_post($realtime_webservice_url_metadata, $params, 30);
			$metadata_array = json_decode($metadata_json, true);
		}
		// Display an error if the web service can't be reached or if the response is not JSON encoded
		if ((isset($metadata_json) && !$metadata_json) || !is_array($metadata_array)) {
			$msg = $lang['ws_159']."<br>";
			if ($metadata_json !== false && !is_array($metadata_array)) {
				$msg .= "<br>".$lang['ws_160'];
			} elseif ($metadata_json === false) {
				$msg .= "<br>".$lang['ws_161'];
			}
			$msg = RCView::div(array('class'=>'red', 'style'=>'margin-top:20px;'), $msg);
			if (SUPER_USER) {
				$msg .= RCView::div(array('style'=>'margin-top:50px;font-size:14px;font-weight:bold;'),
							$lang['global_01'] . $lang['colon']
						) .
						RCView::div(array('style'=>'max-width:800px;border:1px solid #ccc;padding:5px;'), $metadata_json);
			}
			exit($msg);
		}
		// Loop through array of source fields to ensure that one is the identifier
		$num_id_fields = 0;
		foreach ($metadata_array as $key=>$attr) {
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$num_id_fields++;
			}
		}
		if ($num_id_fields != 1) {
			$msg = $lang['ws_159']."<br>";
			if ($num_id_fields == 0) {
				// No id field
				$msg .= "<br>".$lang['ws_162'];
			} elseif ($num_id_fields > 1) {
				// More than one id field
				$msg .= "<br>".$lang['ws_163'];
			}
			$msg .= " ".$lang['ws_164']." (e.g., [{\"field\":\"mrn\",\"identifier\":\"1\",...}])".$lang['period']." ".$lang['ws_165'];
			exit( RCView::div(array('class'=>'red'), $msg));
		}

		// Keep an array with the cat-subcat-fieldname concatenated together for sorting purposes later
		$field_sorter = array();

		// Make sure we have all the correct elements for each field
		$metadata_array2 = $keys = array();
		$id_field_key = null;
		$id_field_name = null;
		foreach ($metadata_array as $key=>$attr)
		{
			$attr2 = array(	'field'=>$attr['field'],
							'temporal'=>($attr['temporal'] == '1' ? 1 : 0),
							'label'=>utf8_encode($attr['label']),
							'description'=> utf8_encode($attr['description']),
							'category'=>$attr['category'],
							'subcategory'=>$attr['subcategory']
						  );
			if (isset($attr['identifier']) && $attr['identifier'] == '1') {
				$attr2['identifier'] = 1;
				// Always set the source id field's cat and subcat to blank so that it's viewed separate from the other fields
				$attr2['category'] = $attr2['subcategory'] = '';
				// Set key value for $id_field_key
				$id_field_key = $key;
				$id_field_name = $attr['field'];
			} else {
				// Add cat-subcat-fieldname concatenated together (don't do this for ID field)
				$field_sorter[] = $attr['category'].'---'.$attr['subcategory'].'---'.$attr['field'];
			}
			// Add to new array
			$metadata_array2[$key] = $attr2;
			$keys[] = $key;
		}
		$metadata_array = $metadata_array2;
		unset($metadata_array2);

		## Put ID field first and then order all cats, subcats, and fields alphabetically
		// Remove ID field for now since we'll add it back at the end
		$id_field_array = $metadata_array[$id_field_key];
		unset($metadata_array[$id_field_key]);

		// Sort all fields by cat, subcat, field name
		array_multisort($field_sorter, SORT_REGULAR, $metadata_array, $keys);

		// Finally, add ID field back to array at beginning
		$metadata_array = array_merge(array($id_field_name=>$id_field_array), $metadata_array);
		$metadata_array = array_combine($keys, $metadata_array);
		
		unset($keys);

		// Return array of fields (or false if not JSON encoded)
		return (is_array($metadata_array) ? $metadata_array : false);
	}




	/**
	 * GET LIST OF FIELDS ALREADY MAPPED TO EXTERNAL SOURCE FIELDS
	 * Return array of fields with external source field as 1st level key, REDCap event_id as 2nd level key,
	 * REDCap field name as 3rd level key,and sub-array of attributes (temporal_field, is_record_identifier).
	 */
	public function getMappedFields()
	{		
		// Make sure Project Attribute class has instantiated the $Proj object
		if ($this->project_id === 0) {
			return array();
		} else {
			$Proj = new Project($this->project_id);
		}

		// If class variable is null, then create mapped field array
		if ($this->field_mappings === null) {
			// Put fields in array
			$this->field_mappings = array();
			// Query table
			$sql = "select * from redcap_ddp_mapping where project_id = ".$this->project_id."
					order by is_record_identifier desc, external_source_field_name, event_id, field_name, temporal_field";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// If event_id is orphaned, then skip it
				if (!isset($Proj->eventInfo[$row['event_id']])) continue;
				// If field is orphaned, then skip it
				if (!isset($Proj->metadata[$row['field_name']])) continue;
				// Initialize sub-array, if not initialized
				if (!isset($this->field_mappings[$row['external_source_field_name']])) {
					$this->field_mappings[$row['external_source_field_name']] = array();
				}
				// Add to array
				$this->field_mappings[$row['external_source_field_name']][$row['event_id']][$row['field_name']] = array(
					'map_id' => $row['map_id'],
					'is_record_identifier' => $row['is_record_identifier'],
					'temporal_field' => $row['temporal_field'],
					'preselect' => $row['preselect']
				);
			}
		}
		// Return the array of field mappings
		return $this->field_mappings;
	}


	/**
	 * RETURN ARRAY OF MAPPED REDCAP FIELDS IF INSTRUMENT HAS ANY REDCAP FIELDS MAPPED TO SOURCE FIELDS FOR THIS FORM/EVENT
	 * Note: This also includes all temporal date/time fields used for mapped fields.
	 */
	public function formEventMappedFields($form_name, $event_id)
	{
		global $Proj;
		// Put fields in array
		$fields = array();
		// Query table
		$fields_keys = isset($Proj->forms[$form_name]) ? array_keys($Proj->forms[$form_name]['fields']) : array();
		$sql = "select field_name, temporal_field from redcap_ddp_mapping where project_id = ".$this->project_id."
				and event_id = $event_id and field_name in (" . prep_implode($fields_keys) . ")";
		$q = db_query($sql);
		if($q !== false)
		{
			while ($row = db_fetch_assoc($q))
			{
				// Add field to array, if on this form
				if (isset($Proj->forms[$form_name]['fields'][$row['field_name']])) {
					$fields[] = $row['field_name'];
				}
				// Add temporal field to array, if not null and on this form
				if ($row['temporal_field'] != '' && isset($Proj->forms[$form_name]['fields'][$row['temporal_field']])) {
					$fields[] = $row['temporal_field'];
				}
			}
		}
		// Make all unique
		$fields = array_unique($fields);
		// Return array
		return $fields;
	}


	/**
	 * OUTPUT JAVASCRIPT FOR TRIGGERING ADJUDICATION POPUP ON DATA ENTRY FORM
	 */
	public function renderJsAdjudicationPopup($record, $event_id=null, $form_name=null, $instance=1)
	{
		global $lang, $Proj, $table_pk_label, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus, $hidden_edit;
		// Make sure RTWS is enabled and user can adjudicate
		if (!((($this->isEnabledInSystem() && $this->isEnabledInProject()) 
			|| ($this->isEnabledInSystemFhir() && $this->isEnabledInProjectFhir())) && $this->userHasAdjudicationRights())) return;
		// Get the REDCap field name mapped to the external source identifier field
		list ($rc_identifier_field, $rc_identifier_event) = $this->getMappedIdRedcapFieldEvent();
		// Get array of mapped REDCap fields for this form/event
		$formEvent_mappedFields = $this->formEventMappedFields($form_name, $event_id);
        // Is this CDP (and not DDP)?
        $isCDP = $this->isEnabledInProjectFhir();
		// If this page is the data entry form AND does NOT have fields mapped to source fields for the event given, then return
		// if ($form_name != null && empty($formEvent_mappedFields)) return;
		// Div for dialog
		print 	RCView::div(array('id'=>'rtws_adjud_popup', 'class'=>'simpleDialog', 'title'=>$lang['ws_27']." ".self::getSourceSystemName()),
					// Instructions
					RCView::a(array('href'=>'javascript:;', 'style'=>'margin:0 0 15px;display:inline-block;text-decoration:underline;', 'onclick'=>"$(this).hide();$('#rtws_adjud_popup_instr').show('fade');"),
						$lang['ws_205']
					) .
					RCView::div(array('id'=>'rtws_adjud_popup_instr', 'style'=>'display:none;margin:0 0 15px;'),
						$lang['ws_166']
					) .
					// Blue context msg bar
					RCView::div(array('class'=>'blue', 'style'=>'font-size:15px;max-width:100%;'),
						RCView::div(array('style'=>'float:left;padding:5px 0 0 5px;'),
							$lang['ws_02'] .
							// Record record name w/ label
							" " . RCView::b($table_pk_label) . " " .
							" \"" . RCView::span(array('id'=>'rtws_adjud_popup_recordname', 'style'=>'font-weight:bold;'), $record) . "\" " .
							// Source ID field label and value
//                            (!$isCDP ? "" :
//                                 RCView::span(array('style'=>'font-weight:bold;color:#800000;'),
//                                     " (MRN " . RCView::span(array('id'=>'rtws_adjud_popup_idval'), '') . ") "
//                                 )
//                            ) .
							// Day offset setting
							RCView::span(array('class'=>'rtws_pullingdates_label'),
								$lang['ws_190'] . RCView::SP .
								RCView::span(array('id'=>'rtws_adjud_popup_day_offset_plusminus_span', 'style'=>'font-weight:bold;margin-right:2px;'),
									($realtime_webservice_offset_plusminus == '+-' ? "&plusmn;" : $realtime_webservice_offset_plusminus)
								) .
								RCView::span(array('id'=>'rtws_adjud_popup_day_offset_span', 'style'=>'font-weight:bold;'),
									$realtime_webservice_offset_days
								) .
								RCView::b(RCView::SP . $lang['scheduling_25'])
							)
						) .
						RCView::div(array('style'=>'float:right;padding-right:5px;'),
							// Button to fetch data again
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:13px;',
								'onclick'=>"if (confirmRefetchSourceData()) triggerRTWSmappedField( $('#rtws_adjud_popup_recordname').text(), true, null, null, true);"),
								'<i class="fas fa-database"></i> ' .
								RCView::span(array('style'=>'vertical-align:middle;'), "Refresh data from" . " " . self::getSourceSystemName())
							) .
							RCView::span(array('class'=>'rtws_pullingdates_label'),
								$lang['ws_190'] . RCView::SP .
								// Dropdown of day offset plus/minus
								"<select id='rtws_adjud_popup_day_offset_plusminus' class='x-form-text x-form-field'>
									<option value='+-' ".($realtime_webservice_offset_plusminus == '+-' ? 'selected' : '').">&plusmn;</option>
									<option value='+' ".($realtime_webservice_offset_plusminus == '+' ? 'selected' : '').">+</option>
									<option value='-' ".($realtime_webservice_offset_plusminus == '-' ? 'selected' : '').">-</option>
								</select>" .
								// Text box for day offset value
								RCView::text(array('id'=>'rtws_adjud_popup_day_offset', 'value'=>$realtime_webservice_offset_days,
									'onblur'=>"redcap_validate(this,'".self::DAY_OFFSET_MIN."','".self::DAY_OFFSET_MAX."','hard','float')", 'class'=>'x-form-text x-form-field',
									'style'=>'width:35px;')) .
								$lang['ws_189']
							)
						) .
						RCView::div(array('class'=>'rtws_pullingdates_label', 'style'=>'clear:both;text-align:right;margin:0 5px 0 0;font-size:11px;'),
							$lang['ws_192']." ".self::DAY_OFFSET_MIN." ".$lang['ws_191']." ".self::DAY_OFFSET_MAX." ".$lang['scheduling_25']
						)
					) .
					// Div for displaying progress
					RCView::div(array('id'=>'rtws_adjud_popup_progress', 'style'=>'padding:30px;font-size:14px;text-align:center;'),
						RCView::img(array('src'=>'progress_circle.gif')) .
						$lang['ws_168']
					) .
					// Div where payload will go
					RCView::div(array('id'=>'rtws_adjud_popup_content', 'style'=>'margin-bottom:10px;'), '')
				);
		// Get array of all date/datetime fields mapped with temporal fields
		$temporalDateFieldsEvents = $this->getTemporalDateFieldsEvents();
		// Obtain an array of the preview fields and set flag if preview fields have been defined
		$preview_fields = $this->getPreviewFields();
		$hasPreviewFields = (!empty($preview_fields));
		// Find all mapped fields displayed on this form/event and set onchange/onclick trigger for autoCheckNewItemsFromSource()
		$jsTrigger = '';
		foreach ($formEvent_mappedFields as $this_field) {
			// Get field type
			$thisFieldType = $Proj->metadata[$this_field]['element_type'];
			// Add db icon for field
			$jsTrigger .=  "addDDPicon('$this_field');\n";
			// RADIO
			if ($thisFieldType == 'radio') {
				$jsTrigger .= "$('form#form :input[name=\"{$this_field}___radio\"]').click(function(){ autoCheckNewItemsFromSource(rtws_record_name, false); });\n";
			}
			// TEXTAREA
			elseif ($thisFieldType == 'textarea') {
				$jsTrigger .= "$('form#form textarea[name=\"{$this_field}\"]').change(function(){ autoCheckNewItemsFromSource(rtws_record_name, false); });\n";
			}
			// ALL OTHER FIELD TYPES
			else {
				// If THIS IS THE IDENTIFIER FIELD, then prompt user to confirm the preview fields before saving the value
				if ($event_id != null && $this_field == $rc_identifier_field) {
					$previewFieldProgressDiv = 	RCView::div(array('style'=>'font-weight:bold;color:#333;margin:10px 0 100px;font-size:13px;'),
													RCView::img(array( 'src'=>'progress_circle.gif')) .
													"Fetching preview data for \"<span id='rtws_idfield_new_record_preview_field_progress_id_value'></span>\"..."
												);
					$jsTrigger .=  "$('form#form :input[name=\"$rc_identifier_field\"]').change(function(){
										$(this).val( trim($(this).val()) );
										if ($(this).val().length == 0) return;
										// Set dialog progress div content
										$('#rtws_idfield_new_record_preview_fields').html('".js_escape($previewFieldProgressDiv)."');
										$('#rtws_idfield_new_record_preview_field_progress_id_value').html( $(this).val() );
										// Open dialog
										$('#rtws_idfield_new_record_warning').dialog({ bgiframe: true, modal: true, width: 500, zIndex: 3999,
											close: function(){ $('form#form :input[name=\"$rc_identifier_field\"]').focus(); } });
										".(!$hasPreviewFields
											? 	"$('#rtws_preview_save_btn').button('enable');"
											: 	"// If user needs to re-connect with EHR, show standalone launch dialog
                                                if ($('#fhir_launch_modal').length && !getCookie('fhir-launch-stop-asking')) {
                                                    showFhirLaunchModal();
                                                    return;
                                                }
											    // Ajax request for preview field data
												$('#rtws_preview_save_btn').button('disable');												
												var ddp_preview_ajax = $.post(app_path_webroot+'DynamicDataPull/preview.php?pid='+pid,{ source_id_value: $(this).val() })
												.done(function(data){
													$('#rtws_idfield_new_record_preview_fields').html(data);
													$('#rtws_preview_save_btn').button('enable');
												});
												// If the validation error popup is displayed because invalid data type on ID field, then close the Preview popup
												setTimeout(function(){
													if ($('#redcapValidationErrorPopup').is(':visible')) {
														$('#rtws_idfield_new_record_warning').dialog('close');
														ddp_preview_ajax.abort();
													}
												},100);"
										)."
									});\n";
				} else {
					// NORMAL TEXT FIELDS
					// If this field is a temporal date field OR is the identifier field, then set it to ALWAYS force a data fetch
					// (since it's value requires more data to be queried from the source system)
					$forceDataFetch = ($event_id != null && ($this_field == $rc_identifier_field
										|| isset($temporalDateFieldsEvents[$event_id][$this_field]))) ? 'true' : 'false';
					$jsTrigger .= "$('form#form :input[name=\"$this_field\"]').blur(function(){ autoCheckNewItemsFromSource(rtws_record_name, $forceDataFetch); });\n";
				}
			}
		}
		## HIDDEN DIALOG TO SAVE FORM IF RECORD HAS NOT BEEN SAVED YET
		// If record has not been saved yet, then give user message to first save the record
		if ($event_id != null)
		{
			print 	RCView::div(array('id'=>'rtws_idfield_new_record_warning', 'class'=>'simpleDialog', 'style'=>'background-color:#EFF6E8;',
						'title'=>($hasPreviewFields ? $lang['ws_169']." ".self::getSourceSystemName() : $lang['ws_172'])),
						// Preview instructions
						RCView::div(array('style'=>($hasPreviewFields ? '' : 'display:none;')),
							RCView::b($lang['ws_170']) . " " . $lang['ws_171']
						) .
						// Instructions when there are NO preview fields
						RCView::div(array('style'=>(!$hasPreviewFields ? '' : 'display:none;').'margin-bottom:25px;'),
							$lang['ws_193']
						) .
						// Div for loading preview field data
						RCView::div(array('id'=>'rtws_idfield_new_record_preview_fields', 'style'=>($hasPreviewFields ? '' : 'display:none;').'margin:20px 0 15px;'),
							''
						) .
						// Save/cancel buttons
						RCView::div(array('style'=>'margin-top:15px;text-align:right;'),
							RCView::button(array('id'=>'rtws_preview_save_btn', 'class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;', 'onclick'=>"
								appendHiddenInputToForm('scroll-top', $(window).scrollTop());
								appendHiddenInputToForm('open-ddp', '1');
								appendHiddenInputToForm('save-and-continue','1');
								$(this).hide();
								$('#rtws_preview_cancel_btn').hide();
								$('#rtws_preview_save_progress_text').show();
								dataEntrySubmit(this);"),
								'<i class="fas fa-database"></i> ' .
								RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;color:#222;'),
									$lang['ws_172']
								)
							) .
							RCView::span(array('id'=>'rtws_preview_save_progress_text', 'style'=>'display:none;font-size:13px;font-weight:bold;margin-right:70px;line-height:18px;'),
								RCView::img(array('style'=>'vertical-align:middle;', 'src'=>'progress_circle.gif')) .
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['ws_173']
								)
							) .
							RCView::button(array('id'=>'rtws_preview_cancel_btn', 'class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;margin-left:3px;', 'onclick'=>"
								$('#rtws_idfield_new_record_warning').dialog('close');"),
								$lang['global_53']
							)
						)
					);
		}
		// Get count of new items to display at top of page when it loads
		$isEHRpage = (strpos($_SERVER['PHP_SELF'], '/ehr.php') !== false);
		if (($hidden_edit && $event_id != null) || PAGE == 'DataEntry/record_home.php' || $isEHRpage)
		{
			## GET NUMBER OF ITEMS TO ADJUDICATE and the html to display inside the dialog
			$itemsToAdjudicate = $this->getNewItemCount($record);
			// If itemsToAdjudicate is null (due to never having been fetch OR if cron just updated it), then determine new count
			if ($itemsToAdjudicate === null && $event_id != null) {
				// Get current data saved for this form
				$form_data = Records::getData('array', $record, array_keys($Proj->forms[$form_name]['fields']), $event_id);
				$form_data = $form_data[$record][$event_id];
				// Get number of items to adjudicate and the html to display inside the dialog
				list ($itemsToAdjudicate, $newItemsTableHtml)
					= $this->fetchAndOutputData($record, $event_id, $form_data, $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus,
												false, true, false, false, $instance, $form_name);
			}
			// Determine which message to display depending on if we have new items to adjudicate
			if ($itemsToAdjudicate == 0) {
				// No new items
				$newItemsText = RCView::img(array('src'=>'information_frame.png')) .
								RCView::span(array('style'=>'color:#000066;'),
									$lang['ws_174']
								) .
								RCView::SP . "(" .
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin:0 1px;font-size:11px;', 'onclick'=>"openAdjudicationDialog('".js_escape($record)."');return false;"),
									$lang['global_84']
								) . ")";
			} else {
				// There are 1 or more new items
				$newItemsText = RCView::div(array('id'=>'RTWS_sourceDataCheck_msgBox', 'class'=>'red', 'style'=>'color:#C00000;text-align:center;font-weight:bold;'),
									RCView::span(array('class'=>'badgerc', 'style'=>'font-size:12px;'), $itemsToAdjudicate) .
									RCView::span(array(),
										$lang['ws_175']
									) .
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin-left:10px;', 'onclick'=>"openAdjudicationDialog('".js_escape($record)."');return false;"),
										$lang['global_84']
									)
								);
			}
		}
		// Javascript
		?>
		<script type='text/javascript'>
		// Set record name
		var rtws_record_name = getParameterByName('id');
		// Language variables
		var langFetchData = '<?php print js_escape($lang['ws_03']) ?>';
		// Display dialog to explain "exclude"
		function rtwsExcludeExplain() {
			simpleDialog('<?php print js_escape($lang['ws_25']) ?>', '<?php print js_escape($lang['ws_24']) ?>');
		}
		$(function() {
			// Perform auto-check of new items from source system and display count at top of data entry form
			var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
			if (page == 'DataEntry/index.php' || isEHRpage || page == 'DataEntry/record_home.php') {
				// Auto run ajax request in background (unless data entry form is locked)
				if (!$('#lock_record_msg').length || page == 'DataEntry/record_home.php') {
					if (record_exists) {
						// Set context msg "new items" count at top of page
						setRTWSContextMsgPlaceholder('<?php echo js_escape($newItemsText) ?>');
						$('#RTWS_sourceDataCheck_msgBox button').button();
					}
					// Set triggers on mapped fields to run on onchange
					<?php print $jsTrigger ?>
				}
			}
		});
		</script>
		<?php
		// Call javascript file
		loadJS('DynamicDataPullAdjudicate.js');
	}


	/**
	 * RETURNS ARRAY OF ALL DATE/DATETIME FIELDS MAPPED WITH TEMPORAL FIELDS
	 * Array will contain event_id as first-level key and date field name as 2nd-level key
	 */
	private function getTemporalDateFieldsEvents()
	{
		$temporalDateFieldsEvents = array();
		$sql = "select distinct event_id, temporal_field from redcap_ddp_mapping
				where project_id = " . $this->project_id . " and temporal_field is not null";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$temporalDateFieldsEvents[$row['event_id']][$row['temporal_field']] = true;
		}
		return $temporalDateFieldsEvents;
	}


	/**
	 * DETERMINE IF DDP IS ENABLED AT THE SYSTEM-LEVEL
	 */
	public function isEnabledInSystem()
	{
		// Get global vars
		global $realtime_webservice_global_enabled;
		// If both vars have a value, then it IS enabled
		return $realtime_webservice_global_enabled;
	}


	/**
	 * DETERMINE IF DDP ON FHIR IS ENABLED AT THE SYSTEM-LEVEL
	 */
	public function isEnabledInSystemFhir()
	{
		// Get global vars
		global $fhir_ddp_enabled;
		// If both vars have a value, then it IS enabled
		return $fhir_ddp_enabled;
	}


	/**
	 * DETERMINE IF RTWS IS ENABLED IN THE CURRENT PROJECT
	 */
	public function isEnabledInProject()
	{
		// Get global vars
		global $realtime_webservice_enabled, $realtime_webservice_type;
		// Return boolean
		return (isset($realtime_webservice_enabled) && $realtime_webservice_enabled && $realtime_webservice_type == 'CUSTOM');
	}


	/**
	 * DETERMINE IF RTWS IS ENABLED IN THE CURRENT PROJECT
	 */
	public function isEnabledInProjectFhir()
	{
		// Get global vars
		global $realtime_webservice_enabled, $realtime_webservice_type;
		// Return boolean
		return (isset($realtime_webservice_enabled) && $realtime_webservice_enabled && $realtime_webservice_type == 'FHIR');
	}


	/**
	 * DETERMINE IF USER HAS RTWS MAPPING PRIVILEGES
	 */
	public function userHasMappingRights()
	{
		// Get global vars
		global $user_rights;
		// Return boolean
		return ($user_rights['realtime_webservice_mapping'] == '1');
	}


	/**
	 * DETERMINE IF USER HAS RTWS ADJUDICATION PRIVILEGES
	 */
	public function userHasAdjudicationRights($checkUserAccessWebService=false)
	{
		// Get global vars
		global $user_rights, $realtime_webservice_type, $fhir_standalone_authentication_flow;
		// Check project-level user rights first
		if (defined("PROJECT_ID") && $user_rights['realtime_webservice_adjudicate'] != '1') return false;
		// Return boolean (check user access web service, if applicable)
		if ($checkUserAccessWebService && $realtime_webservice_type == 'CUSTOM') {
			return $this->userHasAdjudicationRightsWebService();
		} elseif ($realtime_webservice_type == 'FHIR') {
		    if ($fhir_standalone_authentication_flow == '') {
			    return (FhirEhr::isDdpUserAllowlistedForFhir(USERID) && $this->userHasAdjudicationRightsWebService());
		    } else {
				// Don't enforce that the user is in the ehr_user_map table in order to perform standalone launch
				return $this->userHasAdjudicationRightsWebService();
			}
		} else {
			return true;
		}
	}


	/**
	 * CALL "USER ACCESS" WEB SERVICE TO GET "1" (user can adjudicate data from source system) or "0" (user cannot adjudicate data)
	 * Return TRUE if web service returns "1", else return FALSE.
	 * The web service will be called only once per user per project per session and will store a value in the session for checking afterward.
	 * The session variable will be project-specific so that it will be call whenever first accessing the project, which will allow
	 * admins to possibly build project-specific checks (e.g. IRB number) when first accessing a DDP-enabled project.
	 */
	public function userHasAdjudicationRightsWebService()
	{
		// Get global var
		global $realtime_webservice_url_user_access, $fhir_url_user_access, $realtime_webservice_type;
		
		// If user access web service URL is not defined, then always return TRUE
		if ($realtime_webservice_type == 'CUSTOM' && trim($realtime_webservice_url_user_access) == '') 	return true;
		if ($realtime_webservice_type == 'FHIR'   && trim($fhir_url_user_access) == '') 				return true;		

		// Set session variable name
		$session_var_name = 'ddp_user_access_'.$this->project_id;

		// If user has session variable set to 0 or 1, then we've already checked the user web service during this session
		if (!isset($_SESSION[$session_var_name]))
		{
			// CALL WEB SERVICE
			// Set parameters for request
			$params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL);
			// Determine if we're calling the DDP custom service or the DDP FHIR service
			$webservice_url = ($realtime_webservice_type == 'CUSTOM') ? $realtime_webservice_url_user_access : $fhir_url_user_access;
			// Call the URL as POST request
			$response = http_post($webservice_url, $params, 30);
			// Set session value and return true if web service returned "1"
			$_SESSION[$session_var_name] = ($response !== false && trim($response."") === '1') ? '1' : '0';
		}
		
		// Return true if has session value of "1"
		return ($_SESSION[$session_var_name] == '1');
	}


	/**
	 * DETERMINE IF MAPPING HAS BEEN SET UP FOR PROJECT
	 * Check redcap_ddp_mapping table to see if any fields have been mapped.
	 */
	public function isMappingSetUp()
	{
		// Return boolean
		return ($this->getMappedIdFieldExternal() !== false);
	}


	/**
	 * GET THE EXTERNAL FIELD NAME FOR RECORD IDENTIFIER FIELD MAPPED TO THE REDCAP FIELD IN THE PROJECT
	 */
	public function getMappedIdFieldExternal()
	{
		// Query table
		$sql = "select external_source_field_name from redcap_ddp_mapping
				where project_id = ".$this->project_id." and is_record_identifier = 1 limit 1";
		$q = db_query($sql);
		// Return boolean
		return (db_num_rows($q) > 0 ? db_result($q, 0) : false);
	}


	/**
	 * GET THE REDCAP FIELD NAME AND EVENT_ID FOR RECORD IDENTIFIER FIELD MAPPED TO THE EXTERNAL FIELD IN THE PROJECT
	 */
	public function getMappedIdRedcapFieldEvent()
	{
		// Query table
		$sql = "select field_name, event_id from redcap_ddp_mapping
				where project_id = ".$this->project_id." and is_record_identifier = 1 limit 1";
		$q = db_query($sql);
		// Return boolean
		if (db_num_rows($q) > 0) {
			return array(db_result($q, 0, 'field_name'), db_result($q, 0, 'event_id'));
		} else {
			return false;
		}
	}


	/**
	 * EXCLUDE A SOURCE VALUE for a given record during the Adjudication process
	 */
	public function excludeValue($md_id, $exclude)
	{
		global $lang;
		// Make sure we have all the values we need, else return error
		if (($exclude != '0' && $exclude != '1') || !is_numeric($md_id)) return '0';
		// Update table
		$sql = "update redcap_ddp_records_data set exclude = $exclude where md_id = $md_id";
		$q = db_query($sql);
		// Log the event
		if ($q) {
			$log_description = ($exclude) ? "Exclude source value (DDP)" : "Remove exclusion for source value (DDP)";
			Logging::logEvent($sql, "redcap_ddp_records_data", "MANAGE", $md_id, "md_id = $md_id", $log_description);
		}
		// Return label text if true, else '0'
		return ($q ? ($exclude
						? RCView::img(array('src'=>'plus2.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_88']))
						: RCView::img(array('src'=>'cross.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_87']))
					  )
				   : '0');
	}


	/**
	 * GET EXCLUDED VALUES
	 * Return array of md_ids with values already excluded for the given record
	 */
	private function getExcludedValues($record, $map_ids=array())
	{
		// Put all values in array with md_id as array key
		$excluded_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id, d.source_value, d.source_value2
					from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ")
					and r.record = '" . db_escape($record) . "' and r.project_id = " . $this->project_id . "
					and exclude = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) 
			{
				$use_mcrypt = ($row['source_value2'] == '');
				$source_value = $use_mcrypt ? $row['source_value'] : $row['source_value2'];
				$excluded_values_by_md_id[$row['md_id']] = decrypt($source_value, self::DDP_ENCRYPTION_KEY, $use_mcrypt);
			}
		}
		return $excluded_values_by_md_id;
	}


	/**
	 * GET MD_ID'S OF VALUES THAT HAVE *NOT* BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getNonAdjudicatedValues($record, $map_ids=array())
	{
		// Put all values in array with md_id as array key
		$non_adjudicated_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ")
					and r.record = '" . db_escape($record) . "' and r.project_id = " . $this->project_id . "
					and adjudicated = 0";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$non_adjudicated_values_by_md_id[$row['md_id']] = true;
			}
		}
		return $non_adjudicated_values_by_md_id;
	}


	/**
	 * GET MD_ID'S OF VALUES THAT HAVE BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getAdjudicatedValues($record, $map_ids=array())
	{
		// Put all values in array with md_id as array key
		$adjudicated_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ")
					and r.record = '" . db_escape($record) . "' and r.project_id = " . $this->project_id . "
					and adjudicated = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$adjudicated_values_by_md_id[$row['md_id']] = true;
			}
		}
		return $adjudicated_values_by_md_id;
	}


	/**
	 * GET TIME OF LAST DATA FETCH FOR A RECORD
	 * Return timestamp or NULL, if record data has not been cached yet.
	 */
	private function getLastFetchTime($record, $returnInAgoFormat=false)
	{
		global $lang;
		$sql = "select updated_at from redcap_ddp_records
				where project_id = " . $this->project_id . " and record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			$ts = db_result($q, 0);
			// If we're returning the time in "X hours ago" format, then convert it, else return as is
			if ($returnInAgoFormat) {
				// If timestamp is NOW, then return "just now" text
				if ($ts == NOW) return $lang['ws_176'];
				// First convert to minutes
				$ts = (strtotime(NOW) - strtotime($ts))/60;
				// Return if less than 60 minutes
				if ($ts < 60) return ($ts < 1 ? $lang['ws_177'] : (floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_178'] : $lang['ws_179'])));
				// Convert to hours
				$ts = $ts/60;
				// Return if less than 24 hours
				if ($ts < 24) return floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_180'] : $lang['ws_181']);
				// Convert to days and return
				$ts = $ts/24;
				return floor($ts) . " " . (floor($ts) == 1 ? $lang['ws_182'] : $lang['ws_183']);
			}
			// Return value
			return $ts;
		} else {
			return null;
		}
	}


	/**
	 * GET MR_ID FOR A RECORD
	 * Return mr_id primary key for a record in this project. If not exists yet in table, then insert it.
	 */
	private function getMrId($record)
	{
		$sql = "select mr_id from redcap_ddp_records
				where project_id = " . $this->project_id . " and record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			return db_result($q, 0);
		} else {
			$sql = "insert into redcap_ddp_records (project_id, record)
					values (" . $this->project_id . ", '" . db_escape($record) . "')";
			return (db_query($sql) ? db_insert_id() : false);
		}
	}


	/**
	 * GET ANY "PREVIEW" SOURCE FIELDS THAT HAVE BEEN MAPPED
	 * Return array of source fields that have been designated as "preview" fields
	 */
	public function getPreviewFields()
	{
		$sql = "select field1, field2, field3, field4, field5
				from redcap_ddp_preview_fields where project_id = " . $this->project_id;
		$q = db_query($sql);
		if (db_num_rows($q)) {
			// Remove all blank instances
			$preview_fields = db_fetch_assoc($q);
			foreach ($preview_fields as $key=>$field) {
				if ($field == '') unset($preview_fields[$key]);
			}
			return array_values($preview_fields);
		} else {
			return array();
		}
	}


	/**
	 * SEED MR_ID'S FOR ALL RECORDS IN ALL PROJECTS UTILIZING RTWS SERVICE (excluding archived/inactive projects)
	 * Returns count of number of records seeded, else FALSE if query failed.
	 * It adds new row for each record into redcap_ddp_records table.
	 */
	public static function seedMrIdsAllProjects()
	{
		global $realtime_webservice_stop_fetch_inactivity_days, $fhir_stop_fetch_inactivity_days;
		// Validate value of $realtime_webservice_stop_fetch_inactivity_days
		if (!is_numeric($realtime_webservice_stop_fetch_inactivity_days) || $realtime_webservice_stop_fetch_inactivity_days < 1) {
			$realtime_webservice_stop_fetch_inactivity_days = 7;
		}
		if (!is_numeric($fhir_stop_fetch_inactivity_days) || $fhir_stop_fetch_inactivity_days < 1) {
			$fhir_stop_fetch_inactivity_days = 7;
		}
		// Get timestamp of time limit of inactivity for a project
		$xDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$realtime_webservice_stop_fetch_inactivity_days,date("Y")));
		$xDaysAgoFhir = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$fhir_stop_fetch_inactivity_days,date("Y")));
		// First, get all viable projects
		$sql = "select distinct p.project_id from redcap_projects p
				left join redcap_ddp_records r2 on r2.project_id = p.project_id
				where p.status <= 1 and p.realtime_webservice_enabled = 1 and p.date_deleted is null 
				and ((p.last_logged_event is not null and p.last_logged_event > 
					if (p.realtime_webservice_type = 'FHIR', '$xDaysAgoFhir', '$xDaysAgo'))
				or r2.future_date_count > 0)";
		$q = db_query($sql);
		$pids = array();
		while ($row = db_fetch_assoc($q)) {
			$pids[] = $row['project_id'];
		}
		if (empty($pids)) return 0;
		// Seed all mr_id's for all projects using DDP
		$sql = "select x.project_id, x.record from
                (select distinct d.project_id, d.record from redcap_data d where d.project_id in (".prep_implode($pids).")) x
                left join redcap_ddp_records r on x.project_id = r.project_id and x.record = r.record
                where r.record is null";
		$q = db_query($sql);
		$seeded = 0;
		while ($row = db_fetch_assoc($q)) {
			// Add to ddp_records table
			$sql = "insert into redcap_ddp_records (project_id, record)
					values ({$row['project_id']}, '" . db_escape($row['record']) . "')";
			if (db_query($sql)) $seeded++;
		}
		// Return number of records seeded
		return $seeded;
	}


	/**
	 * SET "QUEUED" FETCH STATUS FOR ALL RECORDS IN ALL PROJECTS UTILIZING RTWS SERVICE (excluding archived/inactive projects)
	 * Returns count of number of records that get queued, else FALSE if query failed.
	 */
	public static function setQueuedFetchStatusAllProjects()
	{
		global $realtime_webservice_data_fetch_interval, $realtime_webservice_stop_fetch_inactivity_days,
			   $fhir_data_fetch_interval, $fhir_stop_fetch_inactivity_days;

		// Make sure we have a value for $realtime_webservice_data_fetch_interval
		if (!(is_numeric($realtime_webservice_data_fetch_interval) && $realtime_webservice_data_fetch_interval >= 1)) {
			$realtime_webservice_data_fetch_interval = 24;
		}
		if (!(is_numeric($fhir_data_fetch_interval) && $fhir_data_fetch_interval >= 1)) {
			$fhir_data_fetch_interval = 24;
		}
		
		// Validate value of $realtime_webservice_stop_fetch_inactivity_days
		if (!is_numeric($realtime_webservice_stop_fetch_inactivity_days) || $realtime_webservice_stop_fetch_inactivity_days < 1) {
			$realtime_webservice_stop_fetch_inactivity_days = 7;
		}
		if (!is_numeric($fhir_stop_fetch_inactivity_days) || $fhir_stop_fetch_inactivity_days < 1) {
			$fhir_stop_fetch_inactivity_days = 7;
		}

		// Set fetch interval as specific timestamp in the past
		$fetchIntervalTime = date("Y-m-d H:i:s", (strtotime(NOW)-(3600*$realtime_webservice_data_fetch_interval)));
		$fetchIntervalTimeFhir = date("Y-m-d H:i:s", (strtotime(NOW)-(3600*$fhir_data_fetch_interval)));
		// Get timestamp of time limit of inactivity for a project
		$xDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$realtime_webservice_stop_fetch_inactivity_days,date("Y")));
		$xDaysAgoFhir = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$fhir_stop_fetch_inactivity_days,date("Y")));

		// Get list of records that are ready to be queued (ignore any records where the value is blank for the source ID field).
		// Make sure that we still check records with datetime reference fields with values in the future, even if the project or record has
		// not been modified in the past X days of inactivity.
		$project_mrid_list = array();
		$project_x_days_ago = array();
		$sql = "select r.project_id, r.record, r.mr_id, p.realtime_webservice_type from
				redcap_projects p, redcap_ddp_mapping m, redcap_data d, redcap_ddp_records r
				where p.status <= 1 and p.realtime_webservice_enabled = 1 and p.date_deleted is null
				and ((p.last_logged_event is not null and p.last_logged_event > if (p.realtime_webservice_type = 'FHIR', '$xDaysAgoFhir', '$xDaysAgo') ) 
					or (select count(1) from redcap_ddp_records r2 where r2.project_id = p.project_id and r2.future_date_count > 0) > 0)
				and p.project_id = m.project_id and m.is_record_identifier = 1 and d.project_id = m.project_id
				and d.event_id = m.event_id and d.field_name = m.field_name and r.project_id = m.project_id
				and r.record = d.record and d.value != '' and r.fetch_status is null
				and (r.updated_at is null or r.updated_at <= if (p.realtime_webservice_type = 'FHIR', '$fetchIntervalTimeFhir', '$fetchIntervalTime') )";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to array
			$project_mrid_list[$row['project_id']][$row['record']] = $row['mr_id'];
			if (!isset($project_x_days_ago[$row['project_id']])) {
				$project_x_days_ago[$row['project_id']] = ($row['realtime_webservice_type'] == 'FHIR') ? $xDaysAgoFhir : $xDaysAgo;
			}
		}

		// Keep count of number of records queued
		$numRecordsQueued = 0;
		// Loop through records and return only those modified in past X days (based upon $realtime_webservice_stop_fetch_inactivity_days)
		foreach ($project_mrid_list as $this_project_id=>$records_mrids) {
			// If there are more records than max records per batch, then do in several batches
			$records_log_query = array_chunk($records_mrids, self::RECORD_LIMIT_PER_LOG_QUERY, true);
			// Query log_event table for each batch
			foreach ($records_log_query as $records_mrids_this_batch) {
				// Return a list of records in this batch that have dates of service that exist in the future
				$records_mrids_this_batch_future_dates = self::checkRecordsFutureDates($this_project_id, $records_mrids_this_batch);
				// Return a list of records in this batch that have been modified in past X days
				// (exclude those already found that have future dates - reduces query time)
				$records_mrids_this_batch_NO_future_dates = array_diff_assoc($records_mrids_this_batch, $records_mrids_this_batch_future_dates);
				$records_mrids_this_batch_modified = self::checkRecordsModifiedRecently($this_project_id, $records_mrids_this_batch_NO_future_dates, $project_x_days_ago[$this_project_id]);
				// Merge the mr_id arrays to build the ones that we need to queue in this batch
				$mr_ids_to_queue_this_batch = array_unique(array_merge($records_mrids_this_batch_modified, $records_mrids_this_batch_future_dates));
				// Set the fetch status of the records returned to QUEUED
				if (!empty($mr_ids_to_queue_this_batch))
				{
					$sql = "update redcap_ddp_records set fetch_status = 'QUEUED'
							where mr_id in (" . prep_implode($mr_ids_to_queue_this_batch) . ")";
					if (db_query($sql)) {
						// Increment queued record count
						$numRecordsQueued += db_affected_rows();
					}
				}
			}
		}

		// Return number of recrods queued
		return $numRecordsQueued;
	}


	/**
	 * RETURN A LIST OF RECORDS IN THIS BATCH THAT HAVE DATES OF SERVICE THAT EXIST IN THE FUTURE
	 */
	private static function checkRecordsFutureDates($this_project_id, $records_mrids=array())
	{
		// Query the ddp_records table using the record names in $records_mrids
		$records_mrids_this_batch_future_dates = array();
		$sql = "SELECT record from redcap_ddp_records where project_id = $this_project_id
				and record in (" . prep_implode(array_keys($records_mrids)) . ")
				and (future_date_count > 0 or updated_at is null)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to array with record as key and mr_id as value
			$records_mrids_this_batch_future_dates[$row['record']] = $records_mrids[$row['record']];
		}
		// Return array of ONLY the records modified in past X days
		return $records_mrids_this_batch_future_dates;
	}


	/**
	 * QUERY LOG TABLE TO DETERMINE IF A LIST OF RECORDS HAVE BEEN MODIFIED IN PAST X DAYS
	 * Returns an array of with record name as array key and mr_id as its corresponding value for
	 * records that HAVE been modified in the past X days (based upon $realtime_webservice_stop_fetch_inactivity_days).
	 */
	private static function checkRecordsModifiedRecently($this_project_id, $records_mrids=array(), $x_days_ago)
	{
		// Query the log_event table using the record names in $records_mrids
		$records_mrids_this_batch_modified = array();
		$sql = "select distinct(pk) as record from ".Logging::getLogEventTable($this_project_id)."
				where project_id = $this_project_id
				and event in ('UPDATE', 'INSERT', 'DELETE', 'DOC_UPLOAD', 'DOC_DELETE', 'LOCK_RECORD', 'ESIGNATURE')
				and ts > '$x_days_ago'
				and pk in (" . prep_implode(array_keys($records_mrids)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to array with record as key and mr_id as value
			$records_mrids_this_batch_modified[$row['record']] = $records_mrids[$row['record']];
		}
		// Return array of ONLY the records modified in past X days
		return $records_mrids_this_batch_modified;
	}


	/**
	 * FETCH QUEUED RECORDS FROM SOURCE (up to a certain limit per batch)
	 * For an individual project with RTWS service enabled, query the external source system for every record
	 * whose "updated_at" timestamp is greater than the data fetch interval (e.g. 24 hours).
	 */
	public static function fetchQueuedRecordsFromSource()
	{
		global $redcap_version, $Proj, $project_id;
		// Get list of projects for which to fetch source data
		$project_mrid_list = self::getProjectsWithQueuedRecords();
		// Set all queued records in this batch with "fetching" fetch status
		$num_records_ready_to_fetch = self::setFetchingStatusForQueuedRecords($project_mrid_list);
		// If nothing to fetch, then stop here
		if ($num_records_ready_to_fetch == 0) return;
		// Collect count of total records fetched from the source system
		$num_records_fetched = 0;
		// Loop through each project with data to fetch, processing each one as a single HTTP Get request
		foreach ($project_mrid_list as $this_project_id=>$these_mrids)
		{
			$project_id = $this_project_id;
			// Instantiate Project object for this project and make sure it gets set as global (so that we don't have to recreate the object for EACH RECORD fetched)
			$Proj = new Project($this_project_id);
			// Instantiate DynamicDataPull object for this project
			$DDP = new DynamicDataPull($this_project_id, $Proj->project['realtime_webservice_type']);
			// Get all records=>source ID field values by mr_id
			$sourceIdValues = $DDP->getSourceIdValuesByMrId($these_mrids);
			// Loop through all mr_id's to get its record name and source id field value
			foreach ($sourceIdValues as $mr_id=>$attr) {
				// Ensure that record's fetch_status is still FETCHING (just in case user fetch via front-end while the cron was waiting)
				$sql = "select 1 from redcap_ddp_records where mr_id = $mr_id and fetch_status is not null";
				$q = db_query($sql);
				if (!db_num_rows($q)) continue;
				// Fetch and cache any data from the source system for this record
				$DDP->fetchData($attr['record'], null, $attr['source_id'], $Proj->project['realtime_webservice_offset_days'], $Proj->project['realtime_webservice_offset_plusminus'],
								array(), true, '1', false, $this_project_id);
				// Set fetch_status to NULL. Also set new item count to null since we're not running renderAdjudicationTable(),
				// which saves the proper "new item count" that includes existing data values and excluded values, etc.
				// Setting new item count to null will force it to be set when the user loads a data entry form or views the adjudication popup.
				$sql = "update redcap_ddp_records set fetch_status = null, item_count = null where mr_id = $mr_id";
				db_query($sql);
			}
			// For any mr_id's that are somehow left over and not fetched from the source system, set fetch_status as null
			$other_mrids = array_diff($these_mrids, array_keys($sourceIdValues));
			if (!empty($other_mrids)) {
				$sql = "update redcap_ddp_records set fetch_status = null where mr_id in (" . prep_implode($other_mrids) . ")";
				db_query($sql);
			}
			// Increment the number of records whose data was fetched in the source system
			$num_records_fetched += count($sourceIdValues);
		}
		// Free up memory (since this is a global variable)
		unset($Proj);
		// Return the total number of records fetched from the source system
		return $num_records_fetched;
	}


	/**
	 * GET LIST OF PROJECTS FOR WHICH TO FETCH SOURCE DATA
	 * Returns array of project_id's for the projects with queued records.
	 */
	private static function getProjectsWithQueuedRecords()
	{
		// Get projects with gueued records
		$project_mrid_list = array();
		$sql = "select project_id, mr_id from redcap_ddp_records
				where fetch_status = 'QUEUED' order by updated_at limit " . self::FETCH_LIMIT_PER_BATCH;
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add project_id to array
			$project_mrid_list[$row['project_id']][] = $row['mr_id'];
		}
		return $project_mrid_list;
	}


	/**
	 * SET "FETCHING" FETCH STATUS FOR ALL QUEUED RECORDS IN THIS BATCH
	 *
	 */
	private static function setFetchingStatusForQueuedRecords($project_mrid_list=array())
	{
		// If there are no projects with queued records, then return 0
		if (empty($project_mrid_list)) return 0;
		// Convert $project_mrid_list into array of only mr_id's
		$mr_ids = array();
		foreach ($project_mrid_list as $this_project_id=>$these_mrids) {
			foreach ($these_mrids as $this_mr_id) {
				$mr_ids[] = $this_mr_id;
			}
		}
		// Update the fetch status of records that are ready to be queued
		$sql = "update redcap_ddp_records set fetch_status = 'FETCHING'
				where fetch_status = 'QUEUED' and mr_id in (" . prep_implode($mr_ids) . ")";
		return (db_query($sql)) ? db_affected_rows() : false;
	}


	/**
	 * GET RECORD NAMES AND THE VALUE OF THEIR CORRESPONDING SOURCE SYSTEM ID FIELD USING MR_ID
	 * Input an array of mr_id's and it returns an array of source system id field values with REDCap record names with mr_id as array key.
	 */
	public function getSourceIdValuesByMrId($mr_ids=array())
	{
		// Query to get source ID field values and corresponding record name
		$sourceIdValues = array();
		$sql = "select r.mr_id, d.record, d.value from
				redcap_ddp_mapping m, redcap_data d, redcap_ddp_records r
				where m.project_id = " . $this->project_id . " and m.is_record_identifier = 1 and d.project_id = m.project_id
				and d.event_id = m.event_id and d.field_name = m.field_name and r.project_id = m.project_id
				and r.record = d.record and r.mr_id in (" . prep_implode($mr_ids) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Remove any blank values
			if ($row['value'] == '') continue;
			// Add value to array with record name as key
			$sourceIdValues[$row['mr_id']] = array('record'=>$row['record'], 'source_id'=>$row['value']);
		}
		return $sourceIdValues;
	}


	/**
	 * OUTPUT THE EXPLANATION TEXT FOR DDP FOR DISPLAYING IN AN INFORMATION POPUP
	 */
	public static function getDdpExplanationText($fhir=false)
	{
		global $lang, $realtime_webservice_custom_text, $realtime_webservice_user_rights_super_users_only,
			   $fhir_custom_text, $fhir_user_rights_super_users_only;
		// First, set the video link's html
        if ($fhir) {
            $realtime_webservice_custom_text_dialog =
                RCView::div(array('style' => 'text-align:right;'),
                    '<i class="fas fa-film"></i> ' .
                    RCView::a(array('href' => 'javascript:;', 'style' => 'text-decoration:underline;', 'onclick' => "popupvid('ddp01.swf','".js_escape($lang['ws_285'])."')"), $lang['ws_284'])
                );
        } else {
            $realtime_webservice_custom_text_dialog =
                RCView::div(array('style' => 'text-align:right;'),
                    '<i class="fas fa-film"></i> ' .
                    RCView::a(array('href' => 'javascript:;', 'style' => 'text-decoration:underline;', 'onclick' => "popupvid('ddp01.swf','".js_escape($lang['ws_41'])."')"), $lang['ws_41'])
                );
        }
		// Intro paragraph as different for FHIR vs Custom
		if ($fhir) {
			$user_rights_super_users_only = $fhir_user_rights_super_users_only;
			// Set text (if custom text is provided, then display it instead of stock text)
			if (trim($fhir_custom_text) != '') {
				$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'),
																nl2br(filter_tags($fhir_custom_text)));
			} else {
				$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'),
																$lang['ws_268']);
			}
			$realtime_webservice_custom_text_dialog .= 	RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_211']) . $lang['ws_212'];
			$realtime_webservice_custom_text_dialog .=
				RCView::br() . RCView::br() .
				RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_248']) .
				$lang['ws_249'] . RCView::br() . RCView::br();
		} else {
			$user_rights_super_users_only = $realtime_webservice_user_rights_super_users_only;
			// Set text (if custom text is provided, then display it instead of stock text)
			if (trim($realtime_webservice_custom_text) != '') {
				$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'),
																nl2br(filter_tags($realtime_webservice_custom_text)));
			} else {
				$realtime_webservice_custom_text_dialog .= 	RCView::div(array('id'=>'ddp_info_custom_text'),
																$lang['ws_50']);
			}
			$realtime_webservice_custom_text_dialog .= 	RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_38']) .
														$lang['ws_37'] . " " . $lang['ws_62'];
			$realtime_webservice_custom_text_dialog .=
				RCView::br() . RCView::br() .
				RCView::div(array('style'=>'font-weight:bold;font-size:110%;'), $lang['ws_39']) .
				$lang['ws_40'] . RCView::br() . RCView::br();
		}
		$demography_mc_mapping_html = "";
		if ($fhir) {
			foreach (self::$demography_mc_mapping as $field=>$enum) {
				$enum = trim(str_replace(" | ", "<br>", $enum));
				$demography_mc_mapping_html .= "<pre style='font-size:11px;overflow:auto;max-height:150px;'><u><b>$field</b></u><br>$enum</pre>";
			}
		}
		// Add stock text/instructions about DDP
        if ($fhir) {
            $realtime_webservice_custom_text_dialog .=
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_42']) .
                ($user_rights_super_users_only ? $lang['ws_281'] : $lang['ws_282'] . " " . RCView::b($lang['ws_58'])) . " " .
                $lang['ws_283'] . RCView::br() . RCView::br() .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_44']) .
                $lang['ws_45'] . RCView::br() . RCView::br() .
                (!$fhir ? '' : RCView::div(array('style' => ''), $lang['ws_258'] . RCView::br() . RCView::br() . $demography_mc_mapping_html) . RCView::br()) .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_46']) .
                $lang['ws_47'] . RCView::br() . RCView::br() .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_48']) .
                $lang['ws_49'] . " " . $lang['ws_59'] . " " . RCView::b($lang['ws_60']) . " " . $lang['ws_61'];
        } else {
            $realtime_webservice_custom_text_dialog .=
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_42']) .
                ($user_rights_super_users_only ? $lang['ws_56'] : $lang['ws_57'] . " " . RCView::b($lang['ws_58'])) . " " .
                $lang['ws_43'] . RCView::br() . RCView::br() .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_44']) .
                $lang['ws_45'] . RCView::br() . RCView::br() .
                (!$fhir ? '' : RCView::div(array('style' => ''), $lang['ws_258'] . RCView::br() . RCView::br() . $demography_mc_mapping_html) . RCView::br()) .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_46']) .
                $lang['ws_47'] . RCView::br() . RCView::br() .
                RCView::div(array('style' => 'font-weight:bold;font-size:110%;'), $lang['ws_48']) .
                $lang['ws_49'] . " " . $lang['ws_59'] . " " . RCView::b($lang['ws_60']) . " " . $lang['ws_61'];
        }
		// Return HTML
		return $realtime_webservice_custom_text_dialog;
	}

	
	/**
	 * fetch data using the DDP custom service
	 *
	 * @param array $params
	 * @return FhirData
	 */
	private function fetchDDPCustomData($params=array())
	{
		global $lang, $realtime_webservice_url_data;
		$data = array();
		$errors = array();
		try {
			$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
			$data[] = json_decode($response_json, true);
			return new FhirData($data);
		} catch (\Exception $e) {
			$message = $lang['ws_137']."<br><br>";
			$message .= $lang['ws_139']." $realtime_webservice_url_data.";
			$message .= $e->getMessage();
			$errors[$realtime_webservice_url_data] = new Exception($message, 0, $e);
			return new FhirData($data, $errors);
		}
	}

	/**
	 * OBTAIN DATA FOR THE PREVIEW FIELDS AND DISPLAY IT
	 */
	public function displayPreviewData($record_identifier_external)
	{
		global $lang, $realtime_webservice_url_data;

		if ($record_identifier_external == '') return 'ERROR!';
		
		$errorsHTML = "";

		// Obtain an array of the preview fields
		$preview_fields = $this->getPreviewFields();

		// Loop through fields to put in necessary array format for sending to data web service
		$field_info = array();
		foreach ($preview_fields as $this_preview_field) {
			$field_info[] = array('field'=>$this_preview_field);
		}

		## CALL DATA WEB SERVICE// If using built-in FHIR service, call EHR via SMART on FHIR methods
		if ($this->realtime_webservice_type == 'FHIR') {
			// Call EHR via SMART on FHIR methods and return array of data values returned
			$this->fhirData = $fhir_data = $this->getFhirData($record_identifier_external, $field_info);
			$response_data_array = $fhir_data->getData();
			// Any errors?
			if($fhir_data->hasErrors())
			{
				$errors = $fhir_data->getErrors();

				$errorsHTML = "<div class='red' style='margin:10px 0;'><b><i class='fas fa-exclamation-triangle'></i> {$lang['global_03']}{$lang['colon']}</b> {$lang['ws_246']}<ul style='margin:0;'>";
				foreach ($errors as $error) {
					$errorMessage = $error->getMessage();
					if($detail = $error->getErrorDetail()) $errorMessage .= '<p>'.$detail.'</p>';
					$errorsHTML .= '<li>'.$errorMessage.'</li>';
				}
				$errorsHTML .= "</ul></div>";
			}
		}	
		// Call the custom data web service URL as POST request
		else {
			// Set params to send in POST request (all JSON-encoded in single parameter 'body')
			// $params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL,
							// 'body' => json_encode(array('id'=>$record_identifier_external, 'fields'=>$field_info))
							// );
			$params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL,
							'id'=>$record_identifier_external, 'fields'=>$field_info);
			// Call the URL as POST request
			$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
			// Decode json into array
			$response_data_array = json_decode($response_json, true);
		}

		// Display an error if the web service can't be reached or if the response is not JSON encoded
		if ((isset($response_json) && !$response_json) || !is_array($response_data_array)) {
			$error_msg = $lang['ws_137']."<br><br>";
			if ($response_json !== false && !is_array($response_data_array)) {
				$error_msg .=  $lang['ws_138']."<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
			} elseif ($response_json === false) {
				$error_msg .= $lang['ws_139']." $realtime_webservice_url_data.";
			}
			exit($error_msg);
		}

		// Convert response array of data from web service into other other
		$preview_fields_data = array();
		foreach ($preview_fields as $this_preview_field) {
			// Seed with blank value first
			$preview_fields_data[$this_preview_field] = RCView::span(array('style'=>'font-weight:normal;'),
															"<i>{$lang['ws_184']}</i>"
														);
			// Find this preview field in the response array returned from web service
			foreach ($response_data_array as $attr) {
				if ($attr['field'] == $this_preview_field) {
					$preview_fields_data[$this_preview_field] = $attr['value'];
				}
			}
		}

		## Build HTML table for displaying the preview field data
		// Row for source ID value
		$rows = RCView::tr(array(),
					RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
						$this->getMappedIdFieldExternal() . $lang['colon']
					) .
					RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
						$record_identifier_external .
						// If no data returned, then display warning icon
						(!empty($response_data_array) ? '' :
							RCView::img(array('src'=>'exclamation_red.png', 'style'=>'margin-left:7px;'))
						)
					)
				);
		// Display row for each preview field
		foreach ($preview_fields_data as $this_preview_field=>$this_preview_field_value) {
			$rows .= 	RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
								$this_preview_field . $lang['colon']
							) .
							RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
								$this_preview_field_value
							)
						);
		}
		// Render table
		$html = RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin-bottom:5px;'),
					$lang['ws_185'] . " \"$record_identifier_external\"{$lang['questionmark']}"
				) .
				RCView::table(array('id'=>'rtws_idfield_new_record_preview_table', 'style'=>'table-layout:fixed;margin-left:20px;'), $rows) . 
				$errorsHTML;

		// EXTENDED LOGGING: Log all values that were displayed in the popup to user
		if (!empty($response_data_array)) {
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".db_escape($record_identifier_external)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($response_data_array as $attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp)
							values ($ml_id, '".db_escape($attr['field'])."', ".checkNull($attr['timestamp']).")";
					db_query($sql);
				}
			}
		}

		// Return html
		return $html;
	}


	/**
	 * PURGE THE DATA CACHE OF SOURCE SYSTEM DATA (ONLY IF PROJECT IS ARCHIVED/INACTIVE)
	 * Default will purge all records in project, but if parameter $record is provided, it will purge only that record.
	 */
	public function purgeDataCache($record=null)
	{
		global $status;
		// If project is not archive/inactive status, then return false (but not if doing this for single record)
		if ($status <= 1 && $record === null) return false;
		// Remove all records in mapping_records table
		$sql = "delete from redcap_ddp_records where project_id = " . $this->project_id;
		if ($record !== null) {
			$sql .= " and record = '".db_escape($record)."'";
		}
		if (!db_query($sql)) return false;
		// Log this action if purging ALL records
		if ($record === null) {
			Logging::logEvent($sql, "redcap_ddp_records", "MANAGE", $this->project_id, "project_id = " . $this->project_id, "Remove usused DDP data (DDP)");
		}
		// Return on success or failure
		return true;
	}


	/**
	 * RETURN CUSTOM NAME OF EXTERNAL SOURCE SYSTEM, ELSE RETURN STOCK TEXT IF NOT DEFINED
	 */
	static function getSourceSystemName($fhir=null)
	{
		global $realtime_webservice_source_system_custom_name, $lang, $realtime_webservice_type, $fhir_source_system_custom_name;
		// Return custom EHR name if using DDP on FHIR
		if ($fhir === true && trim($fhir_source_system_custom_name) != '') {
			return $fhir_source_system_custom_name;
		// Return "EHR" if using DDP on FHIR
		} elseif (defined("PROJECT_ID") && $realtime_webservice_type == 'FHIR' && $fhir !== false) {
			return (trim($fhir_source_system_custom_name) == '' ? "EHR" : $fhir_source_system_custom_name);
		// If custom name not defined
		} elseif (trim($realtime_webservice_source_system_custom_name) == '') {
			return $lang['ws_52'];
		// Return custom name
		} else {
			return $realtime_webservice_source_system_custom_name;
		}
	}


	/**
	 * Re-encrypt all the cached DDP data values in batches
	 */
	public static function reencryptCachedData()
	{
		// Set batch amount
		$limit_per_batch = 10000;
		// Find any values that have not been converted
		$sql = "select md_id, source_value 
				from redcap_ddp_records_data where source_value2 is null 
				order by md_id limit $limit_per_batch";
		$q = db_query($sql);
		// Keep tally of number of values that we re-encrypt
		$num_values_encrypted = db_num_rows($q);
		// Loop through values if there are some
		if ($num_values_encrypted > 0) {
			// If OpenSSL is not enabled, then keep returning "-1" so that the cron stays enabled until OpenSSL is finally installed
			if (!openssl_loaded()) return -1;
			// Loop through values
			while ($row = db_fetch_assoc($q))
			{
				// Decrypt source_value using Mcrypt
				$decrypted_value = decrypt($row['source_value'], self::DDP_ENCRYPTION_KEY, true);
				// Re-encrypt the value
				$reencrypted_value = encrypt($decrypted_value, self::DDP_ENCRYPTION_KEY);
				// Add value back to table
				$sql = "update redcap_ddp_records_data set source_value2 = '".db_escape($reencrypted_value)."' 
						where md_id = " . $row['md_id'];
				db_query($sql);
			}
		}
		// Return the number of values that were encrypted
		return $num_values_encrypted;
	}

}
