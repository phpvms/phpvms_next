<?php

namespace Modules\Installer\Services\Importer;

use App\Contracts\Service;
use App\Repositories\KvpRepository;
use Exception;
use Illuminate\Http\Request;
use Modules\Installer\Services\Importer\Importers\AircraftImporter;
use Modules\Installer\Services\Importer\Importers\AirlineImporter;
use Modules\Installer\Services\Importer\Importers\AirportImporter;
use Modules\Installer\Services\Importer\Importers\ClearDatabase;
use Modules\Installer\Services\Importer\Importers\FinalizeImporter;
use Modules\Installer\Services\Importer\Importers\FlightImporter;
use Modules\Installer\Services\Importer\Importers\GroupImporter;
use Modules\Installer\Services\Importer\Importers\PirepImporter;
use Modules\Installer\Services\Importer\Importers\RankImport;
use Modules\Installer\Services\Importer\Importers\UserImport;

class ImporterService extends Service
{
    private $CREDENTIALS_KEY = 'legacy.importer.db';

    /**
     * @var KvpRepository
     */
    private $kvpRepo;

    /**
     * The list of importers, in proper order
     */
    private $importList = [
        ClearDatabase::class,
        RankImport::class,
        GroupImporter::class,
        AirlineImporter::class,
        AircraftImporter::class,
        AirportImporter::class,
        FlightImporter::class,
        UserImport::class,
        PirepImporter::class,
        FinalizeImporter::class,
    ];

    public function __construct()
    {
        $this->kvpRepo = app(KvpRepository::class);
    }

    /**
     * Save the credentials from a request
     *
     * @param \Illuminate\Http\Request $request
     */
    public function saveCredentialsFromRequest(Request $request)
    {
        $creds = [
            'admin_email'  => $request->post('email'),
            'host'         => $request->post('db_host'),
            'port'         => $request->post('db_port'),
            'name'         => $request->post('db_name'),
            'user'         => $request->post('db_user'),
            'pass'         => $request->post('db_pass'),
            'table_prefix' => $request->post('db_prefix'),
        ];

        $this->saveCredentials($creds);
    }

    /**
     * Save the given credentials
     *
     * @param array $creds
     */
    public function saveCredentials(array $creds)
    {
        $creds = array_merge([
            'admin_email'  => '',
            'host'         => '',
            'port'         => '',
            'name'         => '',
            'user'         => '',
            'pass'         => 3306,
            'table_prefix' => 'phpvms_',
        ], $creds);

        $this->kvpRepo->save($this->CREDENTIALS_KEY, $creds);
    }

    /**
     * Get the saved credentials
     */
    public function getCredentials()
    {
        return $this->kvpRepo->get($this->CREDENTIALS_KEY);
    }

    /**
     * Create a manifest of the import. Creates an array with the importer name,
     * which then has a subarray of all of the different steps/stages it needs to run
     */
    public function generateImportManifest()
    {
        $manifest = [];

        foreach ($this->importList as $importerKlass) {
            /** @var \Modules\Installer\Services\Importer\BaseImporter $importer */
            $importer = new $importerKlass();
            $manifest = array_merge($manifest, $importer->getManifest());
        }

        return $manifest;
    }

    /**
     * Run a given stage
     *
     * @param     $importer
     * @param int $start
     *
     * @throws \Exception
     *
     * @return int|void
     */
    public function run($importer, $start = 0)
    {
        if (!in_array($importer, $this->importList)) {
            throw new Exception('Unknown importer "'.$importer.'"');
        }

        /** @var $importerInst \Modules\Installer\Services\Importer\BaseImporter */
        $importerInst = new $importer();
        $importerInst->run($start);
    }
}
