<?php
namespace Google\Analytics;

/* This data all lives in its own class because it slows my IDE way down to
have it mixed in with the code. */
class APITestData {
    public static $TEST_ETAG = '"o-85COrcxoYkAw5itMLG4AKNpMY/2aqXzdfGLTlj_eybvPxwiDCkkIs"';
    public static $TEST_COLUMNS_RESPONSE = <<<EOF
{
 "kind": "analytics#columns",
 "etag": "\"o-85COrcxoYkAw5itMLG4AKNpMY/2aqXzdfGLTlj_eybvPxwiDCkkIs\"",
 "totalResults": 10,
 "attributeNames": [
  "replacedBy",
  "type",
  "dataType",
  "group",
  "status",
  "uiName",
  "appUiName",
  "description",
  "calculation",
  "minTemplateIndex",
  "maxTemplateIndex",
  "premiumMinTemplateIndex",
  "premiumMaxTemplateIndex",
  "allowedInSegments"
 ],
 "items": [
  {
   "id": "ga:userType",
   "kind": "analytics#column",
   "attributes": {
    "type": "DIMENSION",
    "dataType": "STRING",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "User Type",
    "description": "A boolean indicating if a user is new or returning. Possible values: New Visitor, Returning Visitor.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:visitorType",
   "kind": "analytics#column",
   "attributes": {
    "replacedBy": "ga:userType",
    "type": "DIMENSION",
    "dataType": "STRING",
    "group": "User",
    "status": "DEPRECATED",
    "uiName": "User Type",
    "description": "A boolean indicating if a user is new or returning. Possible values: New Visitor, Returning Visitor.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:sessionCount",
   "kind": "analytics#column",
   "attributes": {
    "type": "DIMENSION",
    "dataType": "STRING",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "Count of Sessions",
    "description": "The session index for a user. Each session from a unique user will get its own incremental index starting from 1 for the first session. Subsequent sessions do not change previous session indicies. For example, if a certain user has 4 sessions to your website, sessionCount for that user will have 4 distinct values of '1' through '4'.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:visitCount",
   "kind": "analytics#column",
   "attributes": {
    "replacedBy": "ga:sessionCount",
    "type": "DIMENSION",
    "dataType": "STRING",
    "group": "User",
    "status": "DEPRECATED",
    "uiName": "Count of Sessions",
    "description": "The session index for a user. Each session from a unique user will get its own incremental index starting from 1 for the first session. Subsequent sessions do not change previous session indicies. For example, if a certain user has 4 sessions to your website, sessionCount for that user will have 4 distinct values of '1' through '4'.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:daysSinceLastSession",
   "kind": "analytics#column",
   "attributes": {
    "type": "DIMENSION",
    "dataType": "STRING",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "Days Since Last Session",
    "description": "The number of days elapsed since users last visited your property. Used to calculate user loyalty.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:users",
   "kind": "analytics#column",
   "attributes": {
    "type": "METRIC",
    "dataType": "INTEGER",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "Users",
    "description": "The total number of users for the requested time period."
   }
  },
  {
   "id": "ga:visitors",
   "kind": "analytics#column",
   "attributes": {
    "replacedBy": "ga:users",
    "type": "METRIC",
    "dataType": "INTEGER",
    "group": "User",
    "status": "DEPRECATED",
    "uiName": "Users",
    "description": "The total number of users for the requested time period."
   }
  },
  {
   "id": "ga:newUsers",
   "kind": "analytics#column",
   "attributes": {
    "type": "METRIC",
    "dataType": "INTEGER",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "New Users",
    "description": "The number of users whose session was marked as a first-time session.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:newVisits",
   "kind": "analytics#column",
   "attributes": {
    "replacedBy": "ga:newUsers",
    "type": "METRIC",
    "dataType": "INTEGER",
    "group": "User",
    "status": "DEPRECATED",
    "uiName": "New Users",
    "description": "The number of users whose session was marked as a first-time session.",
    "allowedInSegments": "true"
   }
  },
  {
   "id": "ga:percentNewSessions",
   "kind": "analytics#column",
   "attributes": {
    "type": "METRIC",
    "dataType": "PERCENT",
    "group": "User",
    "status": "PUBLIC",
    "uiName": "% New Sessions",
    "description": "The percentage of sessions by people who had never visited your property before.",
    "calculation": "ga:newUsers / ga:sessions"
   }
  }
]}
EOF;
    /* Note that these expected dimension and metric values are in case-
    insensitive alphabetical order according to their names, which is how we
    will get them back when we ask API instances for them. */
    public static $TEST_EXPECTED_DIMENSIONS = array(
        array(
            'getID' => 'ga:daysSinceLastSession',
            'getName' => 'daysSinceLastSession',
            'isDeprecated' => false,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => null,
            'getType' => 'DIMENSION',
            'getDataType' => 'STRING',
            'getGroup' => 'User',
            'getUIName' => 'Days Since Last Session',
            'getDescription' => 'The number of days elapsed since users last visited your property. Used to calculate user loyalty.',
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:sessionCount',
            'getName' => 'sessionCount',
            'isDeprecated' => false,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => null,
            'getType' => 'DIMENSION',
            'getDataType' => 'STRING',
            'getGroup' => 'User',
            'getUIName' => 'Count of Sessions',
            'getDescription' => "The session index for a user. Each session from a unique user will get its own incremental index starting from 1 for the first session. Subsequent sessions do not change previous session indicies. For example, if a certain user has 4 sessions to your website, sessionCount for that user will have 4 distinct values of '1' through '4'.",
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:userType',
            'getName' => 'userType',
            'isDeprecated' => false,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => null,
            'getType' => 'DIMENSION',
            'getDataType' => 'STRING',
            'getGroup' => 'User',
            'getUIName' => 'User Type',
            'getDescription' => 'A boolean indicating if a user is new or returning. Possible values: New Visitor, Returning Visitor.',
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:visitCount',
            'getName' => 'visitCount',
            'isDeprecated' => true,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => 'ga:sessionCount',
            'getType' => 'DIMENSION',
            'getDataType' => 'STRING',
            'getGroup' => 'User',
            'getUIName' => 'Count of Sessions',
            'getDescription' => "The session index for a user. Each session from a unique user will get its own incremental index starting from 1 for the first session. Subsequent sessions do not change previous session indicies. For example, if a certain user has 4 sessions to your website, sessionCount for that user will have 4 distinct values of '1' through '4'.",
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:visitorType',
            'getName' => 'visitorType',
            'isDeprecated' => true,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => 'ga:userType',
            'getType' => 'DIMENSION',
            'getDataType' => 'STRING',
            'getGroup' => 'User',
            'getUIName' => 'User Type',
            'getDescription' => 'A boolean indicating if a user is new or returning. Possible values: New Visitor, Returning Visitor.',
            'getCalculation' => null
        )
    );
    public static $TEST_EXPECTED_METRICS = array(
        array(
            'getID' => 'ga:newUsers',
            'getName' => 'newUsers',
            'isDeprecated' => false,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => null,
            'getType' => 'METRIC',
            'getDataType' => 'INTEGER',
            'getGroup' => 'User',
            'getUIName' => 'New Users',
            'getDescription' => 'The number of users whose session was marked as a first-time session.',
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:newVisits',
            'getName' => 'newVisits',
            'isDeprecated' => true,
            'isAllowedInSegments' => true,
            'getReplacementColumn' => 'ga:newUsers',
            'getType' => 'METRIC',
            'getDataType' => 'INTEGER',
            'getGroup' => 'User',
            'getUIName' => 'New Users',
            'getDescription' => 'The number of users whose session was marked as a first-time session.',
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:percentNewSessions',
            'getName' => 'percentNewSessions',
            'isDeprecated' => false,
            'isAllowedInSegments' => false,
            'getReplacementColumn' => null,
            'getType' => 'METRIC',
            'getDataType' => 'PERCENT',
            'getGroup' => 'User',
            'getUIName' => '% New Sessions',
            'getDescription' => 'The percentage of sessions by people who had never visited your property before.',
            'getCalculation' => 'ga:newUsers / ga:sessions'
        ),
        array(
            'getID' => 'ga:users',
            'getName' => 'users',
            'isDeprecated' => false,
            'isAllowedInSegments' => false,
            'getReplacementColumn' => null,
            'getType' => 'METRIC',
            'getDataType' => 'INTEGER',
            'getGroup' => 'User',
            'getUIName' => 'Users',
            'getDescription' => 'The total number of users for the requested time period.',
            'getCalculation' => null
        ),
        array(
            'getID' => 'ga:visitors',
            'getName' => 'visitors',
            'isDeprecated' => true,
            'isAllowedInSegments' => false,
            'getReplacementColumn' => 'ga:users',
            'getType' => 'METRIC',
            'getDataType' => 'INTEGER',
            'getGroup' => 'User',
            'getUIName' => 'Users',
            'getDescription' => 'The total number of users for the requested time period.',
            'getCalculation' => null
        )
    );
    public static $TEST_ACCOUNT_SUMMARIES_RESPONSE = <<<EOF
{
  "kind": "analytics#accountSummaries",
  "username": "some user",
  "totalResults": 3,
  "startIndex": 1,
  "itemsPerPage": 50,
  "items": [
    {
      "id": "61687",
      "kind": "analytics#accountSummary",
      "name": "Devo, Inc.",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-61687-1",
          "name": "Club Devo",
          "internalWebPropertyId": "asdf",
          "level": "STANDARD",
          "websiteUrl": "http://www.clubdevo.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "6193857",
              "name": "clubdevo.com",
              "type": "WEB"
            }
          ]
        }
      ]
    },
    {
      "id": "987354",
      "kind": "analytics#accountSummary",
      "name": "Intercontinental Absurdities",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-987354-1",
          "name": "Bizarre Records",
          "internalWebPropertyId": "dajtrtj",
          "level": "PREMIUM",
          "websiteUrl": "http://www.bizarre.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "38947601",
              "name": "Bizarre",
              "type": "WEB"
            },
            {
              "kind": "analytics#profileSummary",
              "id": "54856735",
              "name": "Bizarre (2)",
              "type": "WEB"
            }
          ]
        },
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-987354-2",
          "name": "Barking Pumpkin Records",
          "internalWebPropertyId": "fgj46s6msm",
          "level": "PREMIUM",
          "websiteUrl": "http://www.zappa.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "52674679",
              "name": "Barking Pumpkin",
              "type": "WEB"
            }
          ]
        }
      ]
    },
    {
      "id": "396798",
      "kind": "analytics#accountSummary",
      "name": "The Alphabet Business Concern",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-396798-1",
          "name": "The Alphabet Business Concern",
          "internalWebPropertyId": "gnsggfhjsg",
          "level": "STANDARD",
          "websiteUrl": "http://www.cardiacs.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "763680478",
              "name": "cardiacs.com",
              "type": "WEB"
            }
          ]
        }
      ]
    }
  ]
}
EOF;
    public static $TEST_EXPECTED_ACCOUNT_SUMMARIES = array(
        array(
            'getID' => '61687',
            'getName' => 'Devo, Inc.',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-61687-1',
                    'getName' => 'Club Devo',
                    'getLevel' => 'STANDARD',
                    'getURL' => 'http://www.clubdevo.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '6193857',
                            'getName' => 'clubdevo.com',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        ),
        array(
            'getID' => '987354',
            'getName' => 'Intercontinental Absurdities',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-987354-1',
                    'getName' => 'Bizarre Records',
                    'getLevel' => 'PREMIUM',
                    'getURL' => 'http://www.bizarre.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '38947601',
                            'getName' => 'Bizarre',
                            'getType' => 'WEB'
                        ),
                        array(
                            'getID' => '54856735',
                            'getName' => 'Bizarre (2)',
                            'getType' => 'WEB'
                        )
                    )
                ),
                array(
                    'getID' => 'UA-987354-2',
                    'getName' => 'Barking Pumpkin Records',
                    'getLevel' => 'PREMIUM',
                    'getURL' => 'http://www.zappa.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '52674679',
                            'getName' => 'Barking Pumpkin',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        ),
        array(
            'getID' => '396798',
            'getName' => 'The Alphabet Business Concern',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-396798-1',
                    'getName' => 'The Alphabet Business Concern',
                    'getLevel' => 'STANDARD',
                    'getURL' => 'http://www.cardiacs.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '763680478',
                            'getName' => 'cardiacs.com',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        )
    );
    public static $TEST_ACCOUNT_SUMMARIES_RESPONSE_2 = <<<EOF
{
  "kind": "analytics#accountSummaries",
  "username": "some user",
  "totalResults": 1,
  "startIndex": 1,
  "itemsPerPage": 50,
  "items": [
     {
      "id": "987354",
      "kind": "analytics#accountSummary",
      "name": "Intercontinental Absurdities",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-987354-1",
          "name": "Bizarre Records",
          "internalWebPropertyId": "dajtrtj",
          "level": "PREMIUM",
          "websiteUrl": "http://www.bizarre.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "54856735",
              "name": "Bizarre (2)",
              "type": "WEB"
            }
          ]
        }
      ]
    },
    {
      "id": "396798",
      "kind": "analytics#accountSummary",
      "name": "The Alphabet Business Concern",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-396798-1",
          "name": "The Alphabet Business Concern",
          "internalWebPropertyId": "gnsggfhjsg",
          "level": "STANDARD",
          "websiteUrl": "http://www.cardiacs.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "763680478",
              "name": "cardiacs.com",
              "type": "WEB"
            }
          ]
        }
      ]
    },
    {
      "id": "4967239",
      "kind": "analytics#accountSummary",
      "name": "Some dumb company",
      "webProperties": [
        {
          "kind": "analytics#webPropertySummary",
          "id": "UA-4967239-1",
          "name": "Buttsville",
          "internalWebPropertyId": "rhsgnsfgsfth",
          "level": "STANDARD",
          "websiteUrl": "http://www.butts.com",
          "profiles": [
            {
              "kind": "analytics#profileSummary",
              "id": "427457356",
              "name": "butts.com",
              "type": "WEB"
            }
          ]
        }
      ]
    }
  ]
}
EOF;
    public static $TEST_EXPECTED_ACCOUNT_SUMMARIES_2 = array(
        array(
            'getID' => '987354',
            'getName' => 'Intercontinental Absurdities',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-987354-1',
                    'getName' => 'Bizarre Records',
                    'getLevel' => 'PREMIUM',
                    'getURL' => 'http://www.bizarre.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '54856735',
                            'getName' => 'Bizarre (2)',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        ),
        array(
            'getID' => '396798',
            'getName' => 'The Alphabet Business Concern',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-396798-1',
                    'getName' => 'The Alphabet Business Concern',
                    'getLevel' => 'STANDARD',
                    'getURL' => 'http://www.cardiacs.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '763680478',
                            'getName' => 'cardiacs.com',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        ),
        array(
            'getID' => '4967239',
            'getName' => 'Some dumb company',
            'getWebPropertySummaries' => array(
                array(
                    'getID' => 'UA-4967239-1',
                    'getName' => 'Buttsville',
                    'getLevel' => 'STANDARD',
                    'getURL' => 'http://www.butts.com',
                    'getProfileSummaries' => array(
                        array(
                            'getID' => '427457356',
                            'getName' => 'butts.com',
                            'getType' => 'WEB'
                        )
                    )
                )
            )
        )
    );
    /* Nowdoc syntax is necessary here; otherwise PHP interprets a regex anchor
    in one of the response elements as a variable sigil. */
    public static $TEST_SEGMENTS_RESPONSE = <<<'EOF'
{"kind":"analytics#segments","username":"asdf","totalResults":25,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"-1","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-1","segmentId":"gaid::-1","name":"All Sessions","definition":"","type":"BUILT_IN"},{"id":"-2","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-2","segmentId":"gaid::-2","name":"New Users","definition":"sessions::condition::ga:userType==New Visitor","type":"BUILT_IN"},{"id":"-3","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-3","segmentId":"gaid::-3","name":"Returning Users","definition":"sessions::condition::ga:userType==Returning Visitor","type":"BUILT_IN"},{"id":"-4","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-4","segmentId":"gaid::-4","name":"Paid Traffic","definition":"sessions::condition::ga:medium=~^(cpc|ppc|cpa|cpm|cpv|cpp)$","type":"BUILT_IN"},{"id":"-5","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-5","segmentId":"gaid::-5","name":"Organic Traffic","definition":"sessions::condition::ga:medium==organic","type":"BUILT_IN"},{"id":"-6","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-6","segmentId":"gaid::-6","name":"Search Traffic","definition":"sessions::condition::ga:medium=~^(cpc|ppc|cpa|cpm|cpv|cpp|organic)$","type":"BUILT_IN"},{"id":"-7","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-7","segmentId":"gaid::-7","name":"Direct Traffic","definition":"sessions::condition::ga:medium==(none)","type":"BUILT_IN"},{"id":"-8","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-8","segmentId":"gaid::-8","name":"Referral Traffic","definition":"sessions::condition::ga:medium==referral","type":"BUILT_IN"},{"id":"-9","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-9","segmentId":"gaid::-9","name":"Sessions with Conversions","definition":"sessions::condition::ga:goalCompletionsAll\u003e0","type":"BUILT_IN"},{"id":"-10","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-10","segmentId":"gaid::-10","name":"Sessions with Transactions","definition":"sessions::condition::ga:transactions\u003e0","type":"BUILT_IN"},{"id":"-11","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-11","segmentId":"gaid::-11","name":"Mobile and Tablet Traffic","definition":"sessions::condition::ga:deviceCategory==mobile,ga:deviceCategory==tablet","type":"BUILT_IN"},{"id":"-12","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-12","segmentId":"gaid::-12","name":"Non-bounce Sessions","definition":"sessions::condition::ga:bounces==0","type":"BUILT_IN"},{"id":"-13","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-13","segmentId":"gaid::-13","name":"Tablet Traffic","definition":"sessions::condition::ga:deviceCategory==tablet","type":"BUILT_IN"},{"id":"-14","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-14","segmentId":"gaid::-14","name":"Mobile Traffic","definition":"sessions::condition::ga:deviceCategory==mobile","type":"BUILT_IN"},{"id":"-15","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-15","segmentId":"gaid::-15","name":"Tablet and Desktop Traffic","definition":"sessions::condition::ga:deviceCategory==tablet,ga:deviceCategory==desktop","type":"BUILT_IN"},{"id":"-16","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-16","segmentId":"gaid::-16","name":"Android Traffic","definition":"sessions::condition::ga:operatingSystem==Android","type":"BUILT_IN"},{"id":"-17","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-17","segmentId":"gaid::-17","name":"iOS Traffic","definition":"sessions::condition::ga:operatingSystem=~^(iOS|iPad|iPhone|iPod)$","type":"BUILT_IN"},{"id":"-18","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-18","segmentId":"gaid::-18","name":"Other Traffic (Neither iOS nor Android)","definition":"sessions::condition::ga:operatingSystem!~^(Android|iOS|iPad|iPhone|iPod)$","type":"BUILT_IN"},{"id":"-19","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-19","segmentId":"gaid::-19","name":"Bounced Sessions","definition":"sessions::condition::ga:bounces\u003e0","type":"BUILT_IN"},{"id":"-100","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-100","segmentId":"gaid::-100","name":"Single Session Users","definition":"users::condition::ga:sessions==1","type":"BUILT_IN"},{"id":"-101","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-101","segmentId":"gaid::-101","name":"Multi-session Users","definition":"users::condition::ga:sessions\u003e1","type":"BUILT_IN"},{"id":"-102","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-102","segmentId":"gaid::-102","name":"Converters","definition":"users::condition::ga:goalCompletionsAll\u003e0,ga:transactions\u003e0","type":"BUILT_IN"},{"id":"-103","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-103","segmentId":"gaid::-103","name":"Non-Converters","definition":"users::condition::ga:goalCompletionsAll==0;ga:transactions==0","type":"BUILT_IN"},{"id":"-104","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-104","segmentId":"gaid::-104","name":"Made a Purchase","definition":"users::condition::ga:transactions\u003e0","type":"BUILT_IN"},{"id":"-105","kind":"analytics#segment","selfLink":"https://www.googleapis.com/analytics/v3/management/segments/gaid::-105","segmentId":"gaid::-105","name":"Performed Site Search","definition":"users::sequence::ga:searchKeyword!~^$|^\\(not set\\)$","type":"BUILT_IN"}]}
EOF;
    public static $TEST_EXPECTED_SEGMENTS = array(
        array(
           'getDefinition' => '',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'All Sessions',
           'getID' => -1
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:userType==New Visitor',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'New Users',
           'getID' => -2
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:userType==Returning Visitor',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Returning Users',
           'getID' => -3
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:medium=~^(cpc|ppc|cpa|cpm|cpv|cpp)$',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Paid Traffic',
           'getID' => -4
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:medium==organic',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Organic Traffic',
           'getID' => -5
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:medium=~^(cpc|ppc|cpa|cpm|cpv|cpp|organic)$',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Search Traffic',
           'getID' => -6
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:medium==(none)',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Direct Traffic',
           'getID' => -7
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:medium==referral',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Referral Traffic',
           'getID' => -8
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:goalCompletionsAll>0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Sessions with Conversions',
           'getID' => -9
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:transactions>0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Sessions with Transactions',
           'getID' => -10
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:deviceCategory==mobile,ga:deviceCategory==tablet',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Mobile and Tablet Traffic',
           'getID' => -11
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:bounces==0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Non-bounce Sessions',
           'getID' => -12
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:deviceCategory==tablet',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Tablet Traffic',
           'getID' => -13
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:deviceCategory==mobile',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Mobile Traffic',
           'getID' => -14
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:deviceCategory==tablet,ga:deviceCategory==desktop',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Tablet and Desktop Traffic',
           'getID' => -15
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:operatingSystem==Android',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Android Traffic',
           'getID' => -16
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:operatingSystem=~^(iOS|iPad|iPhone|iPod)$',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'iOS Traffic',
           'getID' => -17
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:operatingSystem!~^(Android|iOS|iPad|iPhone|iPod)$',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Other Traffic (Neither iOS nor Android)',
           'getID' => -18
        ),
        array(
           'getDefinition' => 'sessions::condition::ga:bounces>0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Bounced Sessions',
           'getID' => -19
        ),
        array(
           'getDefinition' => 'users::condition::ga:sessions==1',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Single Session Users',
           'getID' => -100
        ),
        array(
           'getDefinition' => 'users::condition::ga:sessions>1',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Multi-session Users',
           'getID' => -101
        ),
        array(
           'getDefinition' => 'users::condition::ga:goalCompletionsAll>0,ga:transactions>0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Converters',
           'getID' => -102
        ),
        array(
           'getDefinition' => 'users::condition::ga:goalCompletionsAll==0;ga:transactions==0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Non-Converters',
           'getID' => -103
        ),
        array(
           'getDefinition' => 'users::condition::ga:transactions>0',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Made a Purchase',
           'getID' => -104
        ),
        array(
           'getDefinition' => 'users::sequence::ga:searchKeyword!~^$|^\(not set\)$',
           'getType' => 'BUILT_IN',
           'getCreatedTime' => null,
           'getUpdatedTime' => null,
           'getName' => 'Performed Site Search',
           'getID' => -105
        )
    );
    public static $TEST_QUERY_RESPONSE = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:medium&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=500","query":{"start-date":"2015-06-01","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:medium","metrics":["ga:users","ga:organicSearches"],"start-index":1,"max-results":500},"itemsPerPage":500,"totalResults":3,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:medium&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=500","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:medium","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"151","ga:organicSearches":"31"},"rows":[["(none)","57","0"],["organic","33","31"],["referral","61","0"]]}
EOF;
    public static $TEST_QUERY_RESPONSE_COLUMNS = array(
        array(
            "name" => "ga:medium",
            "columnType" => "DIMENSION",
            "dataType"=> "STRING"
        ),
        array(
            "name" => "ga:users",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        ),
        array(
            "name" => "ga:organicSearches",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        )
    );
    public static $TEST_QUERY_RESPONSE_ROWS = array(
        array("(none)", "57", "0"),
        array("organic", "33", "31"),
        array("referral", "61", "0")
    );
    public static $TEST_PAGED_QUERY_RESPONSE_1 = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5","query":{"start-date":"2015-06-01","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":1,"max-results":5},"itemsPerPage":5,"totalResults":27,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5","nextLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"151","ga:organicSearches":"31"},"rows":[["(direct)","57","0"],["aol","1","1"],["articles.latimes.com","1","0"],["blacksintechnology.net","1","0"],["blog.infosecanalytics.com","4","0"]]}
EOF;
    public static $TEST_PAGED_QUERY_RESPONSE_2 = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5","query":{"start-date":"2015-06-01","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":6,"max-results":5},"itemsPerPage":5,"totalResults":27,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5","previousLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5","nextLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=11&max-results=5","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"151","ga:organicSearches":"31"},"rows":[["business.time.com","1","0"],["clarksite.wordpress.com","1","0"],["csirtgadgets.org","1","0"],["cygnus.vzbi.com","1","0"],["databreaches.net","1","0"]]}
EOF;
    /* This is identical to $TEST_PAGED_QUERY_RESPONSE_2 except that it has no
    next link, which is a small difference required for a certain test. */
    public static $TEST_PAGED_QUERY_RESPONSE_2_FINAL = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5","query":{"start-date":"2015-06-01","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":6,"max-results":5},"itemsPerPage":5,"totalResults":27,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5","previousLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"151","ga:organicSearches":"31"},"rows":[["business.time.com","1","0"],["clarksite.wordpress.com","1","0"],["csirtgadgets.org","1","0"],["cygnus.vzbi.com","1","0"],["databreaches.net","1","0"]]}
EOF;
    public static $TEST_PAGED_QUERY_RESPONSE_COLUMNS = array(
        array(
            "name" => "ga:source",
            "columnType" => "DIMENSION",
            "dataType"=> "STRING"
        ),
        array(
            "name" => "ga:users",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        ),
        array(
            "name" => "ga:organicSearches",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        )
    );
    public static $TEST_PAGED_QUERY_RESPONSE_ROWS_1 = array(
        array("(direct)", "57", "0"),
        array("aol", "1", "1"),
        array("articles.latimes.com", "1", "0"),
        array("blacksintechnology.net", "1", "0"),
        array("blog.infosecanalytics.com", "4", "0")
    );
    public static $TEST_PAGED_QUERY_RESPONSE_ROWS_2 = array(
        array("business.time.com", "1", "0"),
        array("clarksite.wordpress.com", "1", "0"),
        array("csirtgadgets.org", "1", "0"),
        array("cygnus.vzbi.com", "1", "0"),
        array("databreaches.net", "1", "0")
    );
    public static $TEST_ITERATIVE_QUERY_RESPONSE_1 = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-01&start-index=1&max-results=10","query":{"start-date":"2015-06-01","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":1,"max-results":10},"itemsPerPage":10,"totalResults":10,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=10","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":true,"sampleSize":"654978","sampleSpace":"6579876","columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"69","ga:organicSearches":"1"},"rows":[["(direct)","57","0"],["aol","1","1"],["articles.latimes.com","1","0"],["blacksintechnology.net","1","0"],["blog.infosecanalytics.com","4","0"],["business.time.com","1","0"],["clarksite.wordpress.com","1","0"],["csirtgadgets.org","1","0"],["cygnus.vzbi.com","1","0"],["databreaches.net","1","0"]]}
EOF;
    public static $TEST_ITERATIVE_QUERY_RESPONSE_2 = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=1&max-results=10","query":{"start-date":"2015-06-02","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":1,"max-results":10},"itemsPerPage":10,"totalResults":17,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=1&max-results=10","nextLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=11&max-results=10","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"83","ga:organicSearches":"30"},"rows":[["dbir-attack-graph.infos.ec","2","0"],["deseretnews.com","1","0"],["dkillion-lx.inca.infoblox.com","1","0"],["feedly.com","3","0"],["google","32","30"],["ios.feeddler.com","1","0"],["linkedin.com","2","0"],["na12.salesforce.com","1","0"],["network-security.alltop.com","1","0"],["news.verizonenterprise.com","3","0"]]}
EOF;
    public static $TEST_ITERATIVE_QUERY_RESPONSE_3 = <<<EOF
{"kind":"analytics#gaData","id":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=11&max-results=10","query":{"start-date":"2015-06-02","end-date":"2015-06-02","ids":"ga:12345","dimensions":"ga:source","metrics":["ga:users","ga:organicSearches"],"start-index":11,"max-results":10},"itemsPerPage":10,"totalResults":17,"selfLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=11&max-results=10","previousLink":"https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users,ga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=1&max-results=10","profileInfo":{"profileId":"12345","accountId":"98765","webPropertyId":"UA-98765-1","internalWebPropertyId":"46897987","profileName":"security Blog","tableId":"ga:12345"},"containsSampledData":false,"columnHeaders":[{"name":"ga:source","columnType":"DIMENSION","dataType":"STRING"},{"name":"ga:users","columnType":"METRIC","dataType":"INTEGER"},{"name":"ga:organicSearches","columnType":"METRIC","dataType":"INTEGER"}],"totalsForAllResults":{"ga:users":"83","ga:organicSearches":"30"},"rows":[["ragriz.me","1","0"],["reddit.com","1","0"],["t.co","8","0"],["theguardian.com","1","0"],["thenextweb.com","2","0"],["vcdb.org","1","0"],["verizonenterprise.com","21","0"]]}
EOF;
    public static $TEST_ITERATIVE_QUERY_RESPONSE_COLUMNS = array(
        array(
            "name" => "ga:source",
            "columnType" => "DIMENSION",
            "dataType"=> "STRING"
        ),
        array(
            "name" => "ga:users",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        ),
        array(
            "name" => "ga:organicSearches",
            "columnType" => "METRIC",
            "dataType" => "INTEGER"
        )
    );
    public static $TEST_ITERATIVE_QUERY_RESPONSE_ROWS_1 = array(
        array("(direct)", "57", "0"),
        array("aol", "1", "1"),
        array("articles.latimes.com", "1", "0"),
        array("blacksintechnology.net", "1", "0"),
        array("blog.infosecanalytics.com", "4", "0"),
        array("business.time.com", "1", "0"),
        array("clarksite.wordpress.com", "1", "0"),
        array("csirtgadgets.org", "1", "0"),
        array("cygnus.vzbi.com", "1", "0"),
        array("databreaches.net", "1", "0")
    );
    public static $TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2 = array(
        array("dbir-attack-graph.infos.ec", "2", "0"),
        array("deseretnews.com", "1", "0"),
        array("dkillion-lx.inca.infoblox.com", "1", "0"),
        array("feedly.com", "3", "0"),
        array("google", "32", "30"),
        array("ios.feeddler.com", "1", "0"),
        array("linkedin.com", "2", "0"),
        array("na12.salesforce.com", "1", "0"),
        array("network-security.alltop.com", "1", "0"),
        array("news.verizonenterprise.com", "3", "0")
    );
    public static $TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3 = array(
        array("ragriz.me", "1", "0"),
        array("reddit.com", "1", "0"),
        array("t.co", "8", "0"),
        array("theguardian.com", "1", "0"),
        array("thenextweb.com", "2", "0"),
        array("vcdb.org", "1", "0"),
        array("verizonenterprise.com", "21", "0")
    );
}
?>