<?php
  require __DIR__ . '/vendor/autoload.php';

  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  $dotenv->load();

  $_input = file_get_contents("php://input");
  $input = json_decode($_input, true);

  if(array_key_exists('inline_query', $input) === true){
    processInlineQuery($input);
  } else if (array_key_exists('message', $input) === true){
    sendMessageWithHelp($input);
  }

  function processInlineQuery($input)
  {
    $inlineQueryID = $input['inline_query']['id'];
    $queryText = $input['inline_query']['query'];
    answerInlineQuery($inlineQueryID, $queryText);
  }

  function answerInlineQuery($inlineQueryID, $queryText){
    $constURL = "https://api.telegram.org/bot".$_ENV['TELEGRAM_BOT_API_TOKEN']."/answerInlineQuery";
    $data = array("inline_query_id" => $inlineQueryID,
                  "results" => generateResults($queryText),
                  "cache_time" => 300);
    $data_string = json_encode($data);

    $ch = curl_init($constURL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
    );

    $result = curl_exec($ch);
    curl_close($ch);
  }

  function generateResults($queryText) {
    $tenor = tenorResults($queryText);
    $imgur = imgurResults($queryText);
    $insta = instaResults();
    $merged = array_merge($imgur, $tenor);
    $merged = array_merge($merged, $insta);
    return $merged;
  }

  function imgurResults($queryText){
    $fetchedFromImgur = receiveRabbitsFromImgur($queryText);
    $allGifs = $fetchedFromImgur['data'];
    $breakKill = 0;

    $result = array();
    while (count($result) < 15 && $breakKill < 50) {
      if($breakKill >= count($allGifs)) {
        break;
      }
      $gallery = $allGifs[$breakKill];
      if(array_key_exists('images', $gallery)) {
        $gallery = $gallery['images'];
      } else {
        $gallery = array($gallery);
      }

      foreach ($gallery as $imageSingleGif) {
        if($imageSingleGif['size'] < 8000000 && array_key_exists('mp4', $imageSingleGif) === true){
          $res = array('type' => 'gif',
                       'id' => strval($imageSingleGif['id']),
                       'gif_url' => $imageSingleGif['mp4'],
                       'thumb_url' => $imageSingleGif['mp4']);
          array_push($result, $res);
        }
      }

      $breakKill++;
    }

    return $result;
  }

  function tenorResults($queryText){
    $fetchedFromTenor = receiveRabbitsFromTenor($queryText);
    $allGifs = $fetchedFromTenor['results'];
    $breakKill = 0;

    $result = array();
    while (count($result) < 25 && $breakKill < 50) {
      $gif = $allGifs[$breakKill];
      $imagesGifs = $gif['media'];

      foreach ($imagesGifs as $imageSingleGif) {
        if(array_key_exists('gif', $imageSingleGif) === true && $imageSingleGif['gif']['size'] < 8000000){
          $res = array('type' => 'gif',
                       'id' => strval(51+$breakKill),
                       'gif_url' => $imageSingleGif['gif']['url'],
                       'thumb_url' => $imageSingleGif['gif']['preview']);
          array_push($result, $res);
        }
      }

      $breakKill++;
    }

    return $result;
  }

  function receiveRabbitsFromImgur($queryText){
    $clientID = $_ENV['IMGUR_CLIENT_ID'];
    $constURL = "https://api.imgur.com/3/gallery/search/time/all/0?q_all=bunny%20rabbit%20".urlencode($queryText)."&q_type=anigif";

    $ch = curl_init($constURL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Client-ID '.$clientID
    ));
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
  }

  function receiveRabbitsFromTenor($queryText){
    $key = $_ENV['TENOR_API_KEY'];
    $constURL = "https://g.tenor.com/v1/search?q=rabbit%20".urlencode($queryText)."&limit=50&key=".$key;

    $ch = curl_init($constURL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
  }

  function instaResults(){
    $picsURLS = receiveRabbitsFromInsta();
    $i = 100;

    $result = array();
    foreach ($picsURLS as $url) {
      $i++;
      $res = array('type' => 'photo',
                   'id' => strval($i),
                   'photo_url' => $url,
                   'thumb_url' => $url);
      array_push($result, $res);
    }

    return $result;
  }

  function receiveRabbitsFromInsta(){
    // https://www.instagram.com/explore/tags/rabbits/?__a=1'
    return array();
    /*$data = json_decode(, true);
    $resources = $data['graphql']['hashtag']['edge_hashtag_to_media']['edges'];
    $resourcesResults = array();
    foreach ($resources as $resource) {
      array_push($resourcesResults, $resource['node']['display_url']);
    }
    file_put_contents('logs', json_encode($resources));
    return array_slice($resourcesResults, 0, 10);*/
  }

  function sendMessageWithHelp($input)
  {
    $userID = $input['message']['from']['id'];
    $message = 'Нажмите на кнопку ниже и выберете чат. Пробаблу кролик 🐰';

    $constURL = "https://api.telegram.org/bot".$_ENV['TELEGRAM_BOT_API_TOKEN']."/sendMessage";
    $data = array("chat_id" => $userID,
                  "text" => escapeMessage($message),
                  "parse_mode" => "MarkdownV2",
                  "reply_markup" => array('inline_keyboard' => array(array(array('text' => 'Выбрать чат',
                                                                                 'switch_inline_query' => '')))));

    $data_string = json_encode($data);

    $ch = curl_init($constURL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
    );

    $result = curl_exec($ch);
    curl_close($ch);
  }

  function escapeMessage($message) {
    $message = str_replace('\n', PHP_EOL, $message);
    $reservedChars = ['!', '.', '(', ')', '-', '+', '#', '=', '<', '>'];
    foreach ($reservedChars as $char) {
      $message = str_replace($char, '\\'.$char, $message);
    }
    return $message;
  }
?>
