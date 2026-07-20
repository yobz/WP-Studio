# Next.js development container. Not a production build stage — see
# docs/adr/0013-docker-development-environment.md; a production image
# is Milestone 19's job.
FROM node:20-alpine

WORKDIR /app

# --ignore-scripts: Husky's "prepare" script (package.json) expects a
# real .git directory, which the build context deliberately excludes
# (see .dockerignore) — git hooks have no meaning inside a container.
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .

EXPOSE 3000

# --hostname 0.0.0.0: Next.js's dev server must listen on all
# interfaces, not just loopback, to be reachable via the published
# port from outside the container.
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]
