<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspaceResolver;
use App\Support\CurrentWorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentWorkspace
{
    public function __construct(
        private readonly CurrentWorkspaceResolver $resolver,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set($this->resolver->resolve($request->user(), $request));

        return $next($request);
    }
}
