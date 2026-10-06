<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

/** Approved public facts only; private booking/account data never enters model context. */
final class ProductFacts
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'Get Sorted connects households in Durban/eThekwini with vetted tradespeople. Only services in the supplied active catalogue can be booked.',
            'Describe a home problem, provide booking details using the secure controls, review the summary and tap Confirm booking to post a job.',
            'Customers may receive up to three itemised quotes and choose one. Quotes and pro availability are not guaranteed.',
            'It is free for customers to request quotes and for pros to join. Siya cannot estimate the cost of a repair; pros quote for the job.',
            'Pros apply and go through vetting before approval. Do not invent specific completed checks, ratings, reviews, customer counts or guarantees.',
            'Pros see the suburb and job details. Street address and contact details are shared only with the pro whose quote the customer accepts.',
            'Customers can add optional photos through the upload control. Siya does not analyse photos.',
            'Guests can describe a problem before signing in. Sign-in and phone verification are needed to book.',
            'Get Sorted is not an emergency service and cannot dispatch a fire brigade, ambulance or police.',
            'Siya is Get Sorted’s AI assistant, not Siya Kolisi or a representative of him or the Springboks. Do not claim endorsement or personal experiences.',
            'For anything not established by these facts, say you do not have a confirmed answer. Do not invent support contacts, legal terms, refunds, fees or payment availability.',
        ];
    }
}
