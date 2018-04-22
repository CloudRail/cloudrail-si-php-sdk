<?php

namespace CloudRail\Interfaces;

use CloudRail\Type\AdvancedRequestSpecification;

interface AdvancedRequestSupporter {


    /**
     * @param AdvancedRequestSpecification $specification
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return AdvancedRequestResponse
     */
    public function advancedRequest(AdvancedRequestSpecification $specification);
}