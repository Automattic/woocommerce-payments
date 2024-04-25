import { Plugin } from "vite";
import { createHash } from "crypto";
import { readFileSync } from "fs";

export default function vitePluginCacheBusting(): Plugin {
  return {
    name: "vite-plugin-cache-busting",
    enforce: "pre",
    async resolveId(source, importer, options) {
      if (source.includes("assets")) {
        const resolution = await this.resolve(source, importer, options);
        if (!resolution || resolution.external) return resolution;
        const file = readFileSync(resolution.id);
        const hash = createHash("sha1")
          .update(file)
          .digest("hex")
          .slice(0, 10);
        return `${resolution.id}?v=${hash}`;
      }
      return null;
    },
  };
}
