<?php

declare(strict_types=1);

class pxDiscovery extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $EVSEs = $this->mDNSDiscoverEVSEs();

        $Values = [];

        foreach ($EVSEs as $EVSE) {

            $AddValue = [
                'IPAddress'             => $EVSE['IPv4'],
                'Domain'                => $EVSE['domainName'],
                'name'                  => $EVSE['deviceName'],
                'AmperageLimit'         => $EVSE['AmperageLimit'],
                'PhaseRotation'         => $EVSE['PhaseRotation'],
                'PhaseSTShutoff'        => $EVSE['PhaseSTShutoff'],
                'PhaseSTTurnon'         => $EVSE['PhaseSTTurnon'],
                'SerialNumber'          => $EVSE['serialNumber']
            ];

            $AddValue['create'] = [
                [
                    'moduleID'      => '{EE92367A-BB8B-494F-A4D2-FAD77290CCF4}',
                    'configuration' => [
                        'Serialnumber' => $EVSE['serialNumber']
                    ]
                ],
                [
                    'moduleID'      => '{6EFF1F3C-DF5F-43F7-DF44-F87EFF149566}',
                    'configuration' => [
                        'Host' => $EVSE['IPv4']
                    ]
                ]

            ];

            $Values[] = $AddValue;
        }
        $Form['actions'][0]['values'] = $Values;
        return json_encode($Form);
    }

    public function mDNSDiscoverEVSEs()
    {
        $mDNSInstanceIDs = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
        $resultServiceTypes = ZC_QueryServiceType($mDNSInstanceIDs[0], '_http._tcp', '');

        $evses = [];
        foreach ($resultServiceTypes as $key => $device) {
            if (strpos(strtolower($device['Name']), 'pulsatrix') !== false) {

                $px = [];
                $pxData = [];
                $deviceInfo = ZC_QueryService($mDNSInstanceIDs[0], $device['Name'], '_http._tcp', 'local.');

                if (!empty($deviceInfo)) {
                    $px['Hostname'] = $deviceInfo[0]['Host'];
                    $px['IPv4'] = $deviceInfo[0]['IPv4'][0];
                    $px['serialNumber'] = $deviceInfo[0]['TXTRecords'][0];

                    if (strpos(strtolower($device['Name']), 'evcc') !== false) {                    
                        $pxData = json_decode($this->readEVSEconfigurationData($deviceInfo[0]['IPv4'][0]), true);

                        $px['deviceName'] = $pxData['controllerName'];
                        $px['domainName'] = $pxData['powerDomainName'];
                        $px['AmperageLimit'] = $pxData['effectiveAmperageLimit'];
                        $px['PhaseRotation'] = $pxData['hasPhaseRotation'];
                        $px['PhaseSTShutoff'] = $pxData['hasPhaseSTShutoff'];
                        $px['PhaseSTTurnon'] = $pxData['hasPhaseSTTurnon'];
                    }else{
                        $px['deviceName'] = "UIC";                        
                        $px['domainName'] = "";
                        $px['AmperageLimit'] = "";
                        $px['PhaseRotation'] = "";
                        $px['PhaseSTShutoff'] = "";
                        $px['PhaseSTTurnon'] = "";
                    }

                    array_push($evses, $px);
                }
            }
        }
        return $evses;
    }

    private function readEVSEconfigurationData($ip)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'http://'.$ip.'/api/v1/configuration');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);                       // Die Antwort bitte nicht an STDOUT
    
        $response = curl_exec($curl);                                           // Hier das Ergebnis
    
        if (curl_errno($curl) > 0) {
            // $errtext = curl_error($curl);
            // echo "ERROR ---> ".$errtext.PHP_EOL;
            $json = "";     
        }else{
            $json = json_decode($response, true);                               // Dekodieren der Antwort
        }
        curl_close($curl);

        // return $json;
        return $response;
    }
}
