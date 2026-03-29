const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const WooCommerceDependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");
const path = require("path");

// Export configuration.
module.exports = {
  ...defaultConfig,
  entry: {
    "frontend/payment-block": "/resources/js/frontend/payment-block.js",
  },
  output: {
    path: path.resolve(__dirname, "assets/js/block"),
    filename: "[name].js",
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== "DependencyExtractionWebpackPlugin"
    ),
    new WooCommerceDependencyExtractionWebpackPlugin(),
  ],
};
