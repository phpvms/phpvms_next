<?php

trait ApiTestTrait
{
    public function assertApiResponse(array $actualData)
    {
        $this->assertApiSuccess();

        $response = json_decode($this->response->getContent(), true);
        $responseData = $response['data'];

        $this->assertNotEmpty($responseData['id']);
        $this->assertModelData($actualData, $responseData);
    }

    public function assertApiSuccess()
    {
        $this->assertResponseOk();
        $this->seeJson(['success' => true]);
    }

    public function assertModelData(array $actualData, array $expectedData)
    {
        foreach ($actualData as $key => $value) {
            $this->assertEquals($actualData[$key], $expectedData[$key]);
        }
    }
}
