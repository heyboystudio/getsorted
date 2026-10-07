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
            'GetSorted connects households in Durban/eThekwini with vetted tradespeople. Only the trades GetSorted currently offers can be booked.',
            'Describe a home problem, then add your address, a date and optional photos with the secure controls, review the summary and tap Confirm booking to post a job.',
            'Your job is shared with vetted pros of the right trade who work near you. Up to five of them can send itemised quotes and you choose one. Quotes and pro availability are not guaranteed.',
            'It is free for customers to request quotes and for pros to join. Siya cannot estimate the cost of a repair; pros quote for the job.',
            'Pros apply and go through vetting before approval. Do not invent specific completed checks, ratings, reviews, customer counts or guarantees.',
            'Pros see the area, roughly how far away the job is, and the facts about the problem. Street address and contact details are shared only with the pro whose quote the customer accepts.',
            'Customers can add optional photos through the upload control. Siya does not analyse photos.',
            'Siya is for signed-in customers: Start a job asks visitors to sign in or create an account, and a verified mobile number is needed to book.',
            'GetSorted does not take payments yet. The customer pays the pro directly for the work, including any deposit in the quote.',
            'Siya is GetSorted’s AI assistant, not Siya Kolisi or a representative of him or the Springboks. Do not claim endorsement or personal experiences.',
            'For anything not established by these facts, say you do not have a confirmed answer. Do not invent support contacts, legal terms, refunds, fees or payment availability.',
        ];
    }
}
