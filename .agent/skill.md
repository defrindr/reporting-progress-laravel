---

name: laravel-system-designer
description: Designs scalable, clean, and production-grade backend systems using Laravel 12. Use when building new systems, refactoring architecture, or reviewing backend design.
----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

When designing a Laravel system, always:

1. **Start from the problem, not the code**

   * Clarify business requirements, constraints, and expected scale
   * Identify core domain entities and relationships
   * Define what success looks like (performance, reliability, cost)

2. **Design the architecture first**

   * Decide structure: monolith, modular monolith, or microservices
   * Separate concerns clearly (Controller → Service → Repository → Model)
   * Avoid fat controllers and god classes

3. **Define clear data flow**

   * Show how data moves from request → processing → response
   * Use DTO or structured data handling when needed
   * Ensure validation, transformation, and serialization are clean

4. **Design database with intent**

   * Normalize first, then denormalize when needed
   * Plan indexes based on query patterns
   * Think about transactions, locking, and consistency

5. **Plan for scale from the beginning**

   * Identify bottlenecks early (DB, I/O, external API)
   * Use caching strategy (Redis, query cache, etc.)
   * Offload heavy tasks to queues

6. **Make it observable**

   * Add logging at critical points
   * Structure logs for debugging
   * Prepare monitoring hooks (errors, performance)

7. **Design for failure**

   * Handle retries, fallbacks, and timeouts
   * Never assume external services are always available
   * Gracefully degrade when needed

8. **Enforce security by design**

   * Validate all inputs (Form Request)
   * Apply proper authentication & authorization
   * Prevent common vulnerabilities (SQL Injection, XSS, CSRF)

9. **Keep code clean and maintainable**

   * Follow SOLID principles
   * Use meaningful naming
   * Extract reusable logic into services or packages

10. **Think deployment & lifecycle**

* Ensure safe migrations (zero downtime mindset)
* Use environment-based configuration
* Prepare CI/CD compatibility

11. **Always explain trade-offs**

* Simplicity vs scalability
* Performance vs cost
* Speed of development vs long-term maintainability

12. **If reviewing code/system**

* Identify anti-patterns (fat controller, tight coupling, N+1)
* Suggest concrete improvements, not just criticism
* Prioritize fixes based on impact

Keep explanations practical, structured, and grounded in real-world production scenarios. Avoid overengineering, but never ignore scalability and maintainability.
