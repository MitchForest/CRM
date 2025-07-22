FROM node:18-alpine

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* bun.lock* ./

# Install dependencies
RUN if [ -f bun.lock ]; then \
    curl -fsSL https://bun.sh/install | bash && \
    /root/.bun/bin/bun install; \
  else \
    npm install; \
  fi

# Copy source code
COPY . .

EXPOSE 3000

# Start development server
CMD if [ -f bun.lock ]; then \
    /root/.bun/bin/bun run dev --host; \
  else \
    npm run dev -- --host; \
  fi