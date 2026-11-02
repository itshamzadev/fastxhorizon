FROM node:20-alpine AS build

WORKDIR /app
ARG REACT_APP_API_BASE_URL=https://api.fastxhorizon.com/api
ARG REACT_APP_API_USE_PROXY=false
ARG VITE_META_PIXEL_ID=
ENV REACT_APP_API_BASE_URL=$REACT_APP_API_BASE_URL
ENV REACT_APP_API_USE_PROXY=$REACT_APP_API_USE_PROXY
ENV REACT_APP_META_PIXEL_ID=$VITE_META_PIXEL_ID

COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/build /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1/ || exit 1
