<?php

namespace App\TravelClick\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupSoapEndpointCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'travelclick:setup-soap
                            {--domain= : The domain where the SOAP endpoint will be hosted}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Set up the TravelClick SOAP endpoint';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $this->info('Setting up TravelClick SOAP endpoint...');

    // Create soap directory if it doesn't exist
    $soapDir = storage_path('soap/wsdl');
    if (!File::isDirectory($soapDir)) {
      File::makeDirectory($soapDir, 0755, true);
    }

    // Get domain from option or ask user
    $domain = $this->option('domain');
    if (!$domain) {
      $domain = $this->ask(
        'What is the domain where the SOAP endpoint will be hosted?',
        config('app.url')
      );
    }

    // Determine the SOAP endpoint URL
    $soapEndpoint = rtrim($domain, '/') . '/api/travelclick/soap';

    // Check if WSDL file exists
    $wsdlFile = $soapDir . '/travelclick.wsdl';
    if (!File::exists($wsdlFile)) {
      $this->error('WSDL template file not found at: ' . $wsdlFile);
      $this->info('Creating a new WSDL file...');

      // Create template WSDL
      $this->createWsdlTemplate($wsdlFile);
    }

    // Read WSDL file
    $wsdlContent = File::get($wsdlFile);

    // Replace SOAP endpoint URL
    $wsdlContent = str_replace('__SOAP_ENDPOINT_URL__', $soapEndpoint, $wsdlContent);

    // Save WSDL file
    File::put($wsdlFile, $wsdlContent);

    // Update config
    $configFile = config_path('travelclick.php');
    if (File::exists($configFile)) {
      $configContent = File::get($configFile);

      // Check if endpoints section exists
      if (preg_match('/\'endpoints\'\s*=>\s*\[\s*(.*?)\s*\]/s', $configContent, $matches)) {
        $endpointsContent = $matches[1];

        // Replace or add the WSDL URL
        $newEndpointsContent = preg_replace(
          '/\'wsdl\'\s*=>\s*\'.*?\'/s',
          "'wsdl' => '{$domain}/api/travelclick/soap/wsdl'",
          $endpointsContent
        );

        if ($newEndpointsContent === $endpointsContent) {
          // If no replacement happened, add the WSDL URL
          $newEndpointsContent = $endpointsContent . "\n        'wsdl' => '{$domain}/api/travelclick/soap/wsdl',";
        }

        // Replace the endpoints section
        $configContent = str_replace(
          $matches[0],
          "'endpoints' => [\n        {$newEndpointsContent}\n    ]",
          $configContent
        );

        // Save the config file
        File::put($configFile, $configContent);
      }
    }

    $this->info('WSDL file configured at: ' . $wsdlFile);
    $this->info('SOAP endpoint URL set to: ' . $soapEndpoint);
    $this->info('WSDL URL set to: ' . $domain . '/api/travelclick/soap/wsdl');

    return Command::SUCCESS;
  }

  /**
   * Create a template WSDL file
   *
   * @param string $wsdlFile
   * @return void
   */
  protected function createWsdlTemplate(string $wsdlFile): void
  {
    $template = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<wsdl:definitions
    name="HTNGService"
    targetNamespace="http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns:tns="http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService"
    xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">

    <wsdl:types>
        <xsd:schema targetNamespace="http://pms-t5.ihotelier.com/HTNGService/services/HTNG2011BService">
            <xsd:element name="HTNG2011B_SubmitRequest">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="request" type="xsd:string"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:element name="HTNG2011B_SubmitRequestResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="return" type="xsd:string"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
        </xsd:schema>
    </wsdl:types>

    <wsdl:message name="HTNG2011B_SubmitRequestRequest">
        <wsdl:part element="tns:HTNG2011B_SubmitRequest" name="parameters"/>
    </wsdl:message>
    <wsdl:message name="HTNG2011B_SubmitRequestResponse">
        <wsdl:part element="tns:HTNG2011B_SubmitRequestResponse" name="parameters"/>
    </wsdl:message>

    <wsdl:portType name="HTNG2011BService">
        <wsdl:operation name="HTNG2011B_SubmitRequest">
            <wsdl:input message="tns:HTNG2011B_SubmitRequestRequest"/>
            <wsdl:output message="tns:HTNG2011B_SubmitRequestResponse"/>
        </wsdl:operation>
    </wsdl:portType>

    <wsdl:binding name="HTNG2011BServiceSoap11" type="tns:HTNG2011BService">
        <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
        <wsdl:operation name="HTNG2011B_SubmitRequest">
            <soap:operation soapAction=""/>
            <wsdl:input>
                <soap:body use="literal"/>
            </wsdl:input>
            <wsdl:output>
                <soap:body use="literal"/>
            </wsdl:output>
        </wsdl:operation>
    </wsdl:binding>

    <wsdl:service name="HTNG2011BService">
        <wsdl:port binding="tns:HTNG2011BServiceSoap11" name="HTNG2011BServiceSoap11">
            <soap:address location="__SOAP_ENDPOINT_URL__"/>
        </wsdl:port>
    </wsdl:service>
</wsdl:definitions>
XML;

    File::put($wsdlFile, $template);
  }
}
